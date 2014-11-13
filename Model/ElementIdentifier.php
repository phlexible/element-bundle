<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Model;

use Phlexible\Bundle\LockBundle\Lock\LockIdentityInterface;
use Phlexible\Component\Identifier\Identifier;

/**
 * Element identifier
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElementIdentifier extends Identifier implements LockIdentityInterface
{
    /**
     * @param int    $eid
     * @param string $language
     */
    public function __construct($eid, $language = null)
    {
        if (null === $language) {
            // do not use empty language in identifier
            parent::__construct($eid);
        } else {
            parent::__construct($eid, $language);
        }
    }
}
