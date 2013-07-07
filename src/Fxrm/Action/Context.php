<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

/**
 * Controller environment context. Instantiate this with configuration settings and use
 * as a factory.
 */
class Context {

    private $serializer;

    public function __construct($serializerMap) {
        $this->serializer = new ContextSerializer($serializerMap);
    }

    public function createForm($id, $endpointUrl, $app, $methodName) {
        return new Form($this->serializer, $id, $endpointUrl, $app, $methodName);
    }

    public function createHandler($className, $callback) {
        return new Handler($this->serializer, $className, $callback);
    }
}

?>
