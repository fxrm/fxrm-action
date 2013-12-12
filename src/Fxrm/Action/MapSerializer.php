<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class MapSerializer implements Serializer {
    private $context;

    function __construct(Context $context) {
        $this->context = $context;
    }

    function export($object) {
        // convert into anonymous object
        $result = (object)null;

        foreach ($object as $n => $v) {
            $result->$n = $this->context->export($v);
        }

        return $result;
    }

    function import($class, $value) {
        throw new \Exception('cannot import simple map objects');
    }
}

?>
