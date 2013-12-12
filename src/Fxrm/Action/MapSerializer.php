<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class MapSerializer implements Serializer {
    function export($ctx, $object) {
        // convert into anonymous object
        $result = (object)null;

        foreach ($object as $n => $v) {
            $result->$n = $ctx->export($v);
        }

        return $result;
    }

    function import($ctx, $class, $value) {
        throw new \Exception('cannot import simple map objects');
    }
}

?>
