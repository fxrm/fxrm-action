<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

/**
 * Form handler for model methods.
 * This variant converts PHP request parameters into function arguments and outputs the result as JSON.
 * Non-AJAX (redirect) mode is supported for reporting results.
 */
class Handler {
    private $serializer;

    function __construct(ContextSerializer $serializer) {
        $this->serializer = $serializer;
    }

    public function invoke($app, $methodName, $exceptionMap) {
        // error -> exception converter
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // ignore errors when @ operator is used
            if ( ! error_reporting()) {
                return false;
            }

            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        try {
            $this->invokeSafe($app, $methodName, $exceptionMap);
        } catch(\Exception $e) {
            // always clean up handler
            restore_error_handler();

            throw $e;
        }

        restore_error_handler();
    }

    private function invokeSafe($app, $methodName, $exceptionMap) {
        // check for GPC slashes kid-gloves
        if (get_magic_quotes_gpc()) {
            throw new \Exception('magic_quotes_gpc mode must not be enabled');
        }

        // @todo check for POST method

        $bodyFunctionInfo = new \ReflectionMethod($app, $methodName);

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
                $apiParameterList[] = $this->serializer->import($class->getName(), $value);
            } catch(\Exception $e) {
                $fieldErrors->$param = $this->exportException($exceptionMap, $e);
            }
        }

        // report field validation errors
        if (count((array)$fieldErrors) > 0) {
            // using dedicated 400 status (bad client request syntax)
            self::report($app, $methodName, $publicRequestValues, 400, json_encode($fieldErrors));
            return;
        }

        // catch any output
        ob_start();

        try {
            $result = $bodyFunctionInfo->invokeArgs($app, $apiParameterList);

            if (ob_get_length() > 0) {
                throw new \Exception('unexpected output');
            }
        } catch(\Exception $e) {
            ob_end_clean();

            // report exception
            // using dedicated 500 status (syntax was OK but server-side error)
            self::report($app, $methodName, $publicRequestValues, 500, json_encode($this->exportException($e)));
            return;
        }

        ob_end_clean();

        // result output
        header('Content-Type: text/json');

        $output = array();
        $this->jsonPrint($result, $output);
        self::report($app, $methodName, $publicRequestValues, 200, join('', $output));
    }

    private static function report($app, $methodName, $fieldValues, $httpStatus, $jsonData) {
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

    private function jsonPrint($result, &$output) {
        if (get_class($result) === 'stdClass') {
            // anonymous object
            $first = true;

            $output[] = '{';
            foreach ($result as $k => $v) {
                if ($first) {
                    $first = false;
                } else {
                    $output[] = ',';
                }

                $output[] = json_encode($k);
                $output[] = ':';
                $this->jsonPrint($v, $output);
            }
            $output[] = '}';
        } elseif (is_array($result)) {
            $first = true;

            $output[] = '[';
            foreach ($result as $v) {
                if ($first) {
                    $first = false;
                } else {
                    $output[] = ',';
                }

                $this->jsonPrint($v, $output);
            }
            $output[] = ']';
        } else {
            // pipe everything else through the exporter
            $output[] = json_encode($this->serializer->export($result));
        }
    }

    private function exportException($exceptionMap, $e) {
        $class = new \ReflectionClass($e);

        foreach ($exceptionMap as $rootClassName => $callback) {
            if ($class->getName() === $rootClassName || $class->isSubclassOf($rootClassName)) {
                return $callback($e);
            }
        }

        // unhandled exception, re-throw
        throw $e;
    }
}

?>
