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
    private $defaultSerializer;

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

        $this->defaultSerializer = new MapSerializer($this);
    }

    public final function invoke($initializer, $methodName, $getParameter, $report) {
        $instance = null;

        try {
            $instance = is_callable($initializer) ? $initializer() : $initializer;
        } catch(\Exception $e) {
            // report exception
            // syntax was OK but server-side error
            $this->exportException($e, $report);
            return;
        }

        $bodyFunctionInfo = new \ReflectionMethod($instance, $methodName);

        // collect necessary parameter data
        $apiParameterList = array();
        $fieldErrors = (object)array();

        foreach ($bodyFunctionInfo->getParameters() as $bodyFunctionParameter) {
            $param = $bodyFunctionParameter->getName();
            $class = $bodyFunctionParameter->getClass();

            $value = $getParameter($param);

            try {
                $apiParameterList[] = $this->import($class === null ? null : $class->getName(), $value);
            } catch(\Exception $e) {
                $this->exportException($e, function ($status, $bodyData) use($fieldErrors) {
                    $fieldErrors->$param = $bodyData;
                });
            }
        }

        // report field validation errors
        if (count((array)$fieldErrors) > 0) {
            // using dedicated 400 status (bad client request syntax)
            $report(400, $fieldErrors);
            return;
        }

        try {
            $result = $bodyFunctionInfo->invokeArgs($instance, $apiParameterList);
        } catch(\Exception $e) {
            // report exception
            // syntax was OK but server-side error
            $this->exportException($e, $report);
            return;
        }

        // result output
        $report(200, $this->export($result));
    }

    private function import($className, $value) {
        // pass through simple values, but otherwise wrap even nulls in business primitives
        // @todo deal with nested structures!
        if ($className === null) {
            return $value;
        }

        return $this->findSerializer($className)->import($className, $value);
    }

    public final function export($object) {
        $className = is_object($object) ? get_class($object) : null;

        if ($className === null) {
            return is_array($object) ? $this->exportArray($object) : $object;
        }

        if ($object === null) {
            return null;
        }

        return $this->findSerializer($className)->export($object);
    }

    private function exportArray($array) {
        $result = array();

        foreach ($array as $object) {
            $result[] = $this->export($object);
        }

        return $result;
    }

    private function exportException($e, $report) {
        $class = new \ReflectionClass($e);

        // @todo instead, check each ancestor
        foreach ($this->exceptionMap as $rootClassName => $callback) {
            if ($class->getName() === $rootClassName || $class->isSubclassOf($rootClassName)) {
                $response = $callback($e);

                if (is_object($response)) {
                    $report($response->status ?: 500, $response->bodyData);
                } else {
                    $report(500, $response);
                }

                return;
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

        return $this->defaultSerializer;
    }
}

?>
