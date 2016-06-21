<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\CommunityBundle\Manager;

use Sulu\Bundle\CommunityBundle\DependencyInjection\Configuration;
use Sulu\Bundle\CommunityBundle\Event\CommunityEvent;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Handles registration, confirmation, password reset and forget.
 */
class CommunityManager
{
    const EVENT_REGISTERED = 'sulu.community.registered';
    const EVENT_CONFIRMED = 'sulu.community.confirmed';
    const EVENT_PASSWORD_FORGOT = 'sulu.community.password_forgot';
    const EVENT_PASSWORD_RESETED = 'sulu.community.password_reseted';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $webspaceKey;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * CommunityManager constructor.
     *
     * @param array $config
     * @param string $webspaceKey
     * @param EventDispatcherInterface $eventDispatcher
     * @param TokenStorageInterface $tokenStorage
     * @param UserManager $userManager
     */
    public function __construct(
        array $config,
        $webspaceKey,
        EventDispatcherInterface $eventDispatcher,
        TokenStorageInterface $tokenStorage,
        UserManager $userManager
    ) {
        $this->config = $config;
        $this->webspaceKey = $webspaceKey;
        $this->eventDispatcher = $eventDispatcher;
        $this->tokenStorage = $tokenStorage;
        $this->userManager = $userManager;
    }

    /**
     * Return the webspace key.
     *
     * @return string
     */
    public function getWebspaceKey()
    {
        return $this->webspaceKey;
    }

    /**
     * Register user for the system.
     *
     * @param User $user
     *
     * @return User
     */
    public function register(User $user)
    {
        // User need locale
        if ($user->getLocale() === null) {
            $user->setLocale('en');
        }

        // Enable User by config
        $user->setEnabled(
            $this->getConfigTypeProperty(Configuration::TYPE_REGISTRATION, Configuration::ACTIVATE_USER)
        );

        // Create Confirmation Key
        $user->setConfirmationKey($this->userManager->getUniqueToken('confirmationKey'));

        // Create User
        $this->userManager->createUser($user, $this->webspaceKey, $this->getConfigProperty(Configuration::ROLE));

        // Event
        $event = new CommunityEvent($user, $this->config);
        $this->eventDispatcher->dispatch(self::EVENT_REGISTERED, $event);

        return $user;
    }

    /**
     * Login user into the system.
     *
     * @param User $user
     * @param Request $request
     *
     * @return UsernamePasswordToken
     */
    public function login(User $user, Request $request)
    {
        if (!$user->getEnabled()) {
            return;
        }

        $token = new UsernamePasswordToken(
            $user,
            null,
            $this->getConfigProperty(Configuration::FIREWALL),
            $user->getRoles()
        );

        $this->tokenStorage->setToken($token);

        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch('security.interactive_login', $event);

        return $token;
    }

    /**
     * Confirm the user registration.
     *
     * @param string $token
     *
     * @return User
     */
    public function confirm($token)
    {
        $user = $this->userManager->findByConfirmationKey($token);

        if (!$user) {
            return;
        }

        // Remove Confirmation Key
        $user->setConfirmationKey(null);
        $user->setEnabled($this->getConfigTypeProperty(Configuration::TYPE_CONFIRMATION, Configuration::ACTIVATE_USER));

        // Event
        $event = new CommunityEvent($user, $this->config);
        $this->eventDispatcher->dispatch(self::EVENT_CONFIRMED, $event);

        return $user;
    }

    /**
     * Generate password reset token and save.
     *
     * @param string $emailUsername
     *
     * @return User
     */
    public function passwordForget($emailUsername)
    {
        $user = $this->userManager->findUser($emailUsername);

        if (!$user) {
            return;
        }

        $user->setPasswordResetToken($this->userManager->getUniqueToken('passwordResetToken'));
        $expireDateTime = (new \DateTime())->add(new \DateInterval('PT24H'));
        $user->setPasswordResetTokenExpiresAt($expireDateTime);
        $user->setPasswordResetTokenEmailsSent(
            $user->getPasswordResetTokenEmailsSent() + 1
        );

        // Event
        $event = new CommunityEvent($user, $this->config);
        $this->eventDispatcher->dispatch(self::EVENT_PASSWORD_FORGOT, $event);

        return $user;
    }

    /**
     * Reset user password token.
     *
     * @param User $user
     *
     * @return User
     */
    public function passwordReset(User $user)
    {
        $user->setPasswordResetTokenExpiresAt(null);
        $user->setPasswordResetToken(null);

        // Event
        $event = new CommunityEvent($user, $this->config);
        $this->eventDispatcher->dispatch(self::EVENT_PASSWORD_RESETED, $event);

        return $user;
    }

    /**
     * Get community webspace config.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get community webspace config property.
     *
     * @param string $property
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getConfigProperty($property)
    {
        if (!array_key_exists($property, $this->config)) {
            throw new \Exception(
                sprintf(
                    'Property "%s" not found for webspace "%s" in Community Manager.',
                    $property,
                    $this->webspaceKey
                )
            );
        }

        return $this->config[$property];
    }

    /**
     * Get community webspace config type property.
     *
     * @param string $type
     * @param string $property
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getConfigTypeProperty($type, $property)
    {
        if (!array_key_exists($type, $this->config) || !array_key_exists($property, $this->config[$type])) {
            throw new \Exception(
                sprintf(
                    'Property "%s" from type "%s" not found for webspace "%s" in Community Manager.',
                    $property,
                    $type,
                    $this->webspaceKey
                )
            );
        }

        return $this->config[$type][$property];
    }
}
