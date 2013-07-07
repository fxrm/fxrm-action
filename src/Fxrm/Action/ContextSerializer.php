<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

class ContextSerializer {
    private $constructCallback;
    private $serializerMap;

    function __construct($constructCallback, $serializerMap) {
        $this->constructCallback = $constructCallback;
        $this->serializerMap = array();

        foreach($serializerMap as $className => $ser) {
            // normalize and check class name
            $class = new \ReflectionClass($className);
            $this->serializerMap[$class->getName()] = $ser;
        }
    }

    function constructArgs($className, $argumentList) {
        return call_user_func($this->constructCallback, $className, $argumentList);
    }

    function import($className, $value) {
        if ($className === null || $value === null) {
            return $value;
        }

        return $this->findSerializer($className)->import($className, $value);
    }

    function export($object) {
        $className = get_class($object);

        if ($className === FALSE || $object === null) {
            return $object;
        }

        return $this->findSerializer($className)->export($object);
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
