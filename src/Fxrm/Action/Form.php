<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

/**
 * Form renderer for model methods.
 * Non-AJAX (redirect) mode is supported for reporting results.
 */
class Form {
    private $serializer;

    private $stage = 0;

    private $url;
    private $paramTypes;
    private $fieldValues;
    private $returnValue, $fieldError, $actionError, $hasReturnValue;

    // @todo endpoint URL should be inferred as a *serialization of app instance + method name*
    function __construct(ContextSerializer $serializer, $id, $endpointUrl, $app, $methodName) {
        $this->serializer = $serializer;

        $classInfo = new \ReflectionClass($app);
        $methodInfo = $classInfo->getMethod($methodName);

        $this->url = $endpointUrl === null ? $endpointUrl : $this->addUrlRedirectHash($endpointUrl, md5($id));
        $this->paramTypes = (object)array();

        foreach ($methodInfo->getParameters() as $param) {
            $paramClass = $param->getClass();
            $this->paramTypes->{$param->getName()} = $paramClass ? $paramClass->getName() : null;
        }

        $this->fieldValues = (object)array();

        // parse errors if given via query-string payload
        // @todo preserve field values across submits! ugh but then URL limits start being hit - cache those temporarily?
        if (isset($_GET['$_']) && $id !== null) {
            $payload = explode("\x00", base64_decode($_GET['$_']), 4);

            if (count($payload) === 4 && $payload[0] === md5($id)) {
                $fieldValues = json_decode($payload[1]);
                $status = $payload[2];
                $data = json_decode($payload[3]);

                $this->fieldValues = (object)$fieldValues;

                $this->returnValue = $status === '200' ? $data : null;
                $this->fieldError = $status === '400' ? $data : null;
                $this->actionError = $status === '500' ? $data : null;

                $this->hasReturnValue = $status === '200';
            }
        }
    }

    function hasReturnValue() {
        return $this->hasReturnValue;
    }

    function getReturnValue() {
        // explicitly encouraging checking status first (null may be valid return value)
        if ( ! $this->hasReturnValue) {
            throw new \Exception('action did not return value');
        }

        return $this->returnValue;
    }

    function getActionError() {
        return $this->actionError;
    }

    function getFieldError($fieldName) {
        if ($this->fieldError === null) {
            return null;
        }

        return isset($this->fieldError->$fieldName) ? $this->fieldError->$fieldName : null;
    }

    function start() {
        if ($this->stage !== 0) {
            throw new \Exception('form already started');
        }

        $this->stage = 1;

        echo '<form action="' . htmlspecialchars($this->url) . '" method="post">';
    }

    private function addUrlRedirectHash($url, $hash) {
        $urlParts = explode('?', $url, 2);

        $baseUrl = $urlParts[0];
        $query = (count($urlParts) === 2 ? $urlParts[1] . '&' : '') . 'redirect=' . rawurlencode($hash);

        return $baseUrl . '?' . $query;
    }

    function field($fieldName, $type, $initialValue = null, $options = null) {
        if ( ! property_exists($this->paramTypes, $fieldName)) {
            throw new \Exception('unknown/duplicate field');
        }

        unset($this->paramTypes->$fieldName);

        $inputValue = property_exists($this->fieldValues, $fieldName) ?
            $this->fieldValues->$fieldName :
            $this->serializer->export($initialValue);

        switch($type) {
            case 'hidden':
            case 'text':
            case 'password':
                $inputName = $type === 'password' ? "$fieldName\$" : $fieldName;

                echo '<input type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($inputName) . '" value="' . htmlspecialchars($inputValue) . '" />';

                break;
            default:
                throw new \Exception('unknown field type');
        }
    }

    function end() {
        if ($this->stage !== 1) {
            throw new \Exception('form not ready to end');
        }

        $this->stage = 2;

        echo '</form>';

        if (count((array)$this->paramTypes) > 0) {
            throw new \Exception('unimplemented form parameters: ' . join(', ', array_keys((array)$this->paramTypes)));
        }
    }
}

?>
