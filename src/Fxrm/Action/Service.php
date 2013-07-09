<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

/**
 * Service implementation that uses HTTP GET query parameters to construct an instance.
 */
class Service {
    private $serializer;
    private $className;
    private $argumentMap;

    function __construct(ContextSerializer $serializer, $className) {
        $this->serializer = $serializer;

        $class = new \ReflectionClass($className);

        $this->className = $class->getName();
        $this->argumentMap = array();

        $constructor = $class->getConstructor();

        foreach ($constructor ? $constructor->getParameters() : array() as $param) {
            $paramName = $param->getName();
            $paramClass = $param->getClass();

            $this->argumentMap[$paramName] = $paramClass ? $paramClass->getName() : null;
        }
    }

    public function getSerializer() {
        return $this->serializer;
    }

    public function getClassName() {
        return $this->className;
    }

    public function generateUrl($baseUrl, $paramMap) {
        $rawParamMap = array();

        foreach ($this->argumentMap as $name => $className) {
            $rawParamMap[$name] = $this->serializer->export($paramMap[$name]);
        }

        return $this->addUrlParams($baseUrl, $rawParamMap);
    }

    public function createInstance() {
        // check for GPC slashes kid-gloves
        if (get_magic_quotes_gpc()) {
            throw new \Exception('magic_quotes_gpc mode must not be enabled');
        }

        // collect arguments
        // @todo deal with these exceptions gracefully? shouldn't it be a 404 though
        $argumentList = array();

        foreach ($this->argumentMap as $name => $className) {
            $value = isset($_GET[$name]) ? $_GET[$name] : null;

            $argumentList[] = $this->serializer->import($className, $value);
        }

        return $this->serializer->constructArgs($this->className, $argumentList);
    }

    private function addUrlParams($url, $rawParamMap) {
        $urlParts = explode('?', $url, 2);

        $baseUrl = $urlParts[0];
        $query = (count($urlParts) === 2 ? $urlParts[1] . '&' : '') . http_build_query($rawParamMap);

        return $baseUrl . '?' . $query;
    }
}

?>
