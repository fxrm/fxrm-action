<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

/**
 * Service form implementation that uses POST parameters to encode arguments and JSON for results.
 * Any service implementation may be passed in.
 */
class Form {
    private $context;

    private $url;
    private $paramTypes;
    private $fieldValues;
    private $returnValue, $fieldError, $actionError, $hasReturnValue;

    public static function setupErrorHandler() {
        // error -> exception converter
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // ignore errors when @ operator is used
            if ( ! error_reporting()) {
                return false;
            }

            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });
    }

    public static function invoke(Context $ctx, $instance, $methodName) {
        // check for GPC slashes kid-gloves
        if (get_magic_quotes_gpc()) {
            throw new \Exception('magic_quotes_gpc mode must not be enabled');
        }

        // public request values are saved raw, before deserialization (the latter may fail)
        $publicRequestValues = (object)array();

        // catch any output
        ob_start();

        $ctx->invoke(function ($param) use($publicRequestValues) {
            $value = null;

            if (isset($_POST[$param])) {
                $value = $_POST[$param];

                // save public values to be sent back as necessary
                $publicRequestValues->$param = $value;
            } elseif (isset($_POST["$param\$"])) {
                $value = $_POST["$param\$"];

                // not sending back private values
                $publicRequestValues->$param = null;
            }

            return $value;
        }, function ($status, $jsonBody) use($publicRequestValues) {
            if (ob_get_length() > 0) {
                throw new \Exception('unexpected output');
            }

            ob_end_clean();

            // non-AJAX mode
            if (isset($_GET['redirect']) && isset($_SERVER['HTTP_REFERER'])) {
                // identify which form on the originating page this is intended for
                $formSignature = $_GET['redirect'];

                // work with referer URL query-string
                $urlParts = explode('?', $_SERVER['HTTP_REFERER'], 2);

                $query = count($urlParts) > 1 ? $urlParts[1] : '';

                // remove old payload
                $queryParts = $query === '' ? array() : array_filter(explode('&', $query), function ($q) {
                    return substr($q, 0, 3) !== '$_=';
                });

                $queryParts[] = '$_=' . base64_encode(join("\x00", array($formSignature, json_encode($publicRequestValues), $httpStatus, $jsonData)));

                // using the dedicated 303 response type
                header('HTTP/1.1 303 See Other');
                header('Location: ' . $urlParts[0] . '?' . join('&', $queryParts));
                return;
            }

            // AJAX mode
            $statusLabels = array(
                200 => 'Success',
                400 => 'Bad Syntax',
                500 => 'Internal Error'
            );

            header('HTTP/1.1 ' . $httpStatus . ' ' . $statusLabels[$httpStatus]);
            header('Content-Type: text/json');
            echo $jsonData;
        });
    }

    function __construct(Context $ctx, $url, $className, $methodName, $formDifferentiator = null) {
        $this->context = $ctx;

        $methodInfo = new \ReflectionMethod($className, $methodName);

        // basing form signature on service URL, since it is unique to this service instance + method by definition
        $formSignature = md5(json_encode(array($url, $formDifferentiator)));

        $this->url = $this->addUrlRedirectHash($url, $formSignature);
        $this->paramTypes = (object)array();

        foreach ($methodInfo->getParameters() as $param) {
            $paramClass = $param->getClass();
            $this->paramTypes->{$param->getName()} = $paramClass ? $paramClass->getName() : null;
        }

        $this->fieldValues = (object)array();

        // parse errors if given via query-string payload
        // @todo preserve field values across submits! ugh but then URL limits start being hit - cache those temporarily?
        if (isset($_GET['$_'])) {
            $payload = explode("\x00", base64_decode($_GET['$_']), 4);

            if (count($payload) === 4 && $payload[0] === $formSignature) {
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

    function getUrl() {
        return $this->url;
    }

    function getSuccessStatus() {
        return $this->hasReturnValue;
    }

    function getSuccessValue() {
        // explicitly encourage to check first - null may be valid return value
        if ( ! $this->hasReturnValue) {
            throw new \Exception('no return value');
        }

        return $this->returnValue;
    }

    function getError() {
        return $this->actionError;
    }

    function getFieldExists($fieldName) {
        return property_exists($this->paramTypes, $fieldName);
    }

    function getFieldValue($fieldName, $initialValue = null) {
        if ( ! property_exists($this->paramTypes, $fieldName)) {
            throw new \Exception('unknown field ' . $fieldName);
        }

        return property_exists($this->fieldValues, $fieldName) ?
            $this->fieldValues->$fieldName :
            $this->context->export($initialValue);
    }

    function getFieldError($fieldName) {
        if ( ! property_exists($this->paramTypes, $fieldName)) {
            throw new \Exception('unknown field ' . $fieldName);
        }

        return $this->fieldError === null ?
            null :
            (property_exists($this->fieldError, $fieldName) ? $this->fieldError->$fieldName : null);
    }

    private function addUrlRedirectHash($url, $hash) {
        $urlParts = explode('?', $url, 2);

        $baseUrl = $urlParts[0];
        $query = (count($urlParts) === 2 ? $urlParts[1] . '&' : '') . 'redirect=' . rawurlencode($hash);

        return $baseUrl . '?' . $query;
    }
}

?>
