<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class StoreIdSerializer implements Serializer {
    private $store;

    function __construct(\Fxrm\Store\Environment $store) {
        $this->store = $store;
    }

    function export($ctx, $object) {
        return (string)$this->store->export($object);
    }

    function import($ctx, $class, $value) {
        return $this->store->import($class, $value);
    }
}

?>
