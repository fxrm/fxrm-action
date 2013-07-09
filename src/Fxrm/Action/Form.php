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
    private $serializer;

    private $url;
    private $paramTypes;
    private $fieldValues;
    private $returnValue, $fieldError, $actionError, $hasReturnValue;

    private static $PRIVATE_SUFFIX = ':private';

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

    public static function invoke(Service $service, $methodName) {
        // check for GPC slashes kid-gloves
        if (get_magic_quotes_gpc()) {
            throw new \Exception('magic_quotes_gpc mode must not be enabled');
        }

        $bodyFunctionInfo = new \ReflectionMethod($service->getClassName(), $methodName);

        // collect necessary parameter data
        $apiParameterList = array();

        // public request values are saved raw, before deserialization (the latter may fail)
        $publicRequestValues = (object)array();
        $fieldErrors = (object)array();

        foreach ($bodyFunctionInfo->getParameters() as $bodyFunctionParameter) {
            $param = $bodyFunctionParameter->getName();
            $class = $bodyFunctionParameter->getClass();

            $value = null;

            // try corresponding parameter name, or append a $ for private (e.g. password) fields
            if (isset($_POST[$param])) {
                $value = $_POST[$param];

                // save public values to be sent back as necessary
                $publicRequestValues->$param = $value;
            } elseif (isset($_POST["$param\$"])) {
                $value = $_POST["$param\$"];

                // not sending back private values
                $publicRequestValues->$param = null;
            }

            try {
                $apiParameterList[] = $service->getSerializer()->import($class === null ? null : $class->getName(), $value);
            } catch(\Exception $e) {
                $fieldErrors->$param = $service->getSerializer()->exportException($e);
            }
        }

        // report field validation errors
        if (count((array)$fieldErrors) > 0) {
            // using dedicated 400 status (bad client request syntax)
            self::report($publicRequestValues, 400, json_encode($fieldErrors));
            return;
        }

        // catch any output
        ob_start();

        try {
            $instance = $service->createInstance();
        } catch(\Exception $e) {
            ob_end_clean();

            // report exception
            // using dedicated 400 status (bad client request syntax)
            self::report($publicRequestValues, 400, json_encode($service->getSerializer()->exportException($e)));
            return;
        }

        try {
            $result = $bodyFunctionInfo->invokeArgs($instance, $apiParameterList);

            if (ob_get_length() > 0) {
                throw new \Exception('unexpected output');
            }
        } catch(\Exception $e) {
            ob_end_clean();

            // report exception
            // using dedicated 500 status (syntax was OK but server-side error)
            self::report($publicRequestValues, 500, json_encode($service->getSerializer()->exportException($e)));
            return;
        }

        ob_end_clean();

        // result output
        header('Content-Type: text/json');

        self::report($publicRequestValues, 200, json_encode($service->getSerializer()->export($result)));
    }

    private static function report($fieldValues, $httpStatus, $jsonData) {
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

            $queryParts[] = '$_=' . base64_encode(join("\x00", array($formSignature, json_encode($fieldValues), $httpStatus, $jsonData)));

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
    }

    function __construct(Service $service, $baseUrl, $paramMap, $methodName, $initialValueMap, $formDifferentiator = null) {
        $this->serializer = $service->getSerializer();

        $classInfo = new \ReflectionClass($service->getClassName());
        $methodInfo = $classInfo->getMethod($methodName);

        $serviceUrl = $service->generateUrl($baseUrl, $paramMap);

        // basing form signature on service URL, since it is unique to this service instance by definition
        $formSignature = md5(json_encode(array($serviceUrl, $methodName, $formDifferentiator)));

        $this->url = $this->addUrlRedirectHash($serviceUrl, $formSignature);
        $this->paramTypes = (object)array();
        $this->initialValueMap = (object)array();

        foreach ($methodInfo->getParameters() as $param) {
            $paramClass = $param->getClass();
            $paramName = $param->getName();
            $this->paramTypes->{$paramName} = $paramClass ? $paramClass->getName() : null;
            $this->initialValueMap->{$paramName} = isset($initialValueMap[$paramName]) ? $initialValueMap[$paramName] : null;
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

    function url() {
        return $this->url;
    }

    function success() {
        return $this->hasReturnValue ? (object)array('value' => $this->returnValue) : null;
    }

    function error() {
        return $this->actionError;
    }

    function __isset($fieldName) {
        $isPrivate = substr($fieldName, -strlen(self::$PRIVATE_SUFFIX)) === self::$PRIVATE_SUFFIX;

        if ($isPrivate) {
            $fieldName = substr($fieldName, 0, -strlen(self::$PRIVATE_SUFFIX));
        }

        return property_exists($this->paramTypes, $fieldName);
    }

    function __get($fieldName) {
        $isPrivate = substr($fieldName, -strlen(self::$PRIVATE_SUFFIX)) === self::$PRIVATE_SUFFIX;

        if ($isPrivate) {
            $fieldName = substr($fieldName, 0, -strlen(self::$PRIVATE_SUFFIX));
        }

        if ( ! property_exists($this->paramTypes, $fieldName)) {
            throw new \Exception('unknown field ' . $fieldName);
        }

        return (object)array(
            'inputName' => $isPrivate ? "$fieldName\$" : $fieldName,
            'inputValue' => property_exists($this->fieldValues, $fieldName) ?
                $this->fieldValues->$fieldName :
                $this->serializer->export($this->initialValueMap->$fieldName), // @todo initial value set
            'error' => $this->fieldError === null ?
                null :
                (property_exists($this->fieldError, $fieldName) ? $this->fieldError->$fieldName : null)
        );
    }

    private function addUrlRedirectHash($url, $hash) {
        $urlParts = explode('?', $url, 2);

        $baseUrl = $urlParts[0];
        $query = (count($urlParts) === 2 ? $urlParts[1] . '&' : '') . 'redirect=' . rawurlencode($hash);

        return $baseUrl . '?' . $query;
    }
}

?>
