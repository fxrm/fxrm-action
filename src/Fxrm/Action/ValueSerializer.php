<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class ValueSerializer implements Serializer {
    function export($ctx, $object) {
        return (string)$object;
    }

    function import($ctx, $class, $value) {
        return new $class($value);
    }
}

?>
