<?php

namespace Pim\Bundle\MagentoConnectorBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Is the given credentials valid ?
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @Annotation
 */
class HasValidCredentials extends Constraint
{
    public $messageUsername = 'The given magento api username is invalid';
    public $messageApikey   = 'The given magento api key is invalid';

    /**
     *{@inheritDoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     *{@inheritDoc}
     */
    public function validatedBy()
    {
        return 'has_valid_magento_credentials';
    }
}
