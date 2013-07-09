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

    public function __construct($constructCallback, $serializerMap, $exceptionMap) {
        $this->serializer = new ContextSerializer($constructCallback, $serializerMap, $exceptionMap);
    }

    public function createService($className) {
        return new Service($this->serializer, $className);
    }
}

?>
