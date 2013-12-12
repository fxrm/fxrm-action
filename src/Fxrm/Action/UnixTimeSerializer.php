<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class UnixTimeSerializer implements Serializer {
    function export($object) {
        if ($object instanceof \DateTime) {
            return $object->getTimestamp();
        }

        throw new \Exception('expecting DateTime');
    }

    function import($class, $value) {
        if ($class !== 'DateTime') {
            throw new \Exception('must expect DateTime');
        }

        return new \DateTime('@' . (int)$value);
    }
}

?>
