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

    function export($object) {
        return (string)$this->store->export($object);
    }

    function import($class, $value) {
        return $this->store->import($class, $value);
    }
}

?>
