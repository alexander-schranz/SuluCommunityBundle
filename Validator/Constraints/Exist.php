<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\CommunityBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class Exist extends Constraint
{
    /**
     * @var string
     */
    public $message = 'The value "%string%" was not found.';

    /**
     * @var array
     */
    public $columns = [];

    /**
     * @var string
     */
    public $entity = '';

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'exist_validator';
    }
}
