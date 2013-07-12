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
    private $serializerMap;

    function __construct($serializerMap, $exceptionMap) {
        $this->serializerMap = array();
        $this->exceptionMap = array();

        foreach($serializerMap as $className => $ser) {
            // normalize and check class name
            $class = new \ReflectionClass($className);
            $this->serializerMap[$class->getName()] = $ser;
        }

        foreach($exceptionMap as $className => $callback) {
            // normalize and check class name
            $class = new \ReflectionClass($className);
            $this->exceptionMap[$class->getName()] = $callback;
        }
    }

    function import($className, $value) {
        if ($className === null || $value === null) {
            return $value;
        }

        return $this->findSerializer($className)->import($className, $value);
    }

    function export($object) {
        $className = is_object($object) ? get_class($object) : null;

        if ($className === null || $object === null) {
            return $object;
        }

        return $this->findSerializer($className)->export($object);
    }

    function exportException($e) {
        $class = new \ReflectionClass($e);

        foreach ($this->exceptionMap as $rootClassName => $callback) {
            if ($class->getName() === $rootClassName || $class->isSubclassOf($rootClassName)) {
                return $callback($e);
            }
        }

        // unhandled exception, re-throw
        throw $e;
    }

    private function findSerializer($className) {
        $class = new \ReflectionClass($className);

        foreach ($this->serializerMap as $rootClassName => $ser) {
            if ($class->getName() === $rootClassName || $class->isSubclassOf($rootClassName)) {
                return $ser;
            }
        }

        throw new \Exception('cannot find serializer for import: ' . $className); // developer error
    }
}

?>
