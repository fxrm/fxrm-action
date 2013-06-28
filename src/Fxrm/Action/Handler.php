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
    public static function invoke($app, $internFunc, $externFunc) {
        // error -> exception converter
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // ignore errors when @ operator is used
            if ( ! error_reporting()) {
                return false;
            }

            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        try {
            self::invokeSafe($app, $internFunc, $externFunc);
        } catch(\Exception $e) {
            // always clean up handler
            restore_error_handler();

            throw $e;
        }

        restore_error_handler();
    }

    private static function invokeSafe($app, $internFunc, $externFunc) {
        // check for GPC slashes kid-gloves
        if (get_magic_quotes_gpc()) {
            throw new \Exception('magic_quotes_gpc mode must not be enabled');
        }

        // @todo check for POST method

        // get the method corresponding to current route
        $methodName = isset($_SERVER['PATH_INFO']) ? substr($_SERVER['PATH_INFO'], 1) : '';

        $bodyFunctionInfo = new \ReflectionMethod($app, $methodName);

        // collect necessary parameter data
        $apiParameterList = array();

        $publicRequestValues = (object)array();
        $fieldErrors = (object)array();

        foreach ($bodyFunctionInfo->getParameters() as $bodyFunctionParameter) {
            $param = $bodyFunctionParameter->getName();
            $class = $bodyFunctionParameter->getClass();

            $value = null;

            // @todo IMPORTANT: the "_" in private params is actually supposed to be a dot (Unix-y metaphor) - manually parse query? or use other marker
            if (isset($_REQUEST[$param])) {
                $value = $_REQUEST[$param];

                // save public values to be sent back as necessary
                $publicRequestValues->$param = $value;
            } elseif (isset($_REQUEST["_$param"])) {
                $value = $_REQUEST["_$param"];

                // not sending back private values
                $publicRequestValues->$param = null;
            }

            try {
                $apiParameterList[] = $class ? $internFunc($class->getName(), $value) : $value;
            } catch(\Exception $e) {
                $fieldErrors->$param = $externFunc($e);
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
            self::report($app, $methodName, $publicRequestValues, 500, json_encode($externFunc($e)));
            return;
        }

        ob_end_clean();

        // result output
        header('Content-Type: text/json');

        $output = array();
        self::jsonPrint($result, $externFunc, $output);
        self::report($app, $methodName, $publicRequestValues, 200, join('', $output));
    }

    private static function report($app, $methodName, $fieldValues, $httpStatus, $jsonData) {
        // non-AJAX mode
        if (isset($_GET['redirect']) && isset($_SERVER['HTTP_REFERER'])) {
            // hiding internal implementation names
            // @todo consider better ways to uniquely identify a form submission
            $formSignature = md5(get_class($app) . "\x00" . $methodName);

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

    private static function jsonPrint($result, $externFunc, &$output) {
        if (is_object($result)) {
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
                    self::jsonPrint($v, $externFunc, $output);
                }
                $output[] = '}';
            } else {
                // identity instance
                $output[] = json_encode($externFunc($result));
            }
        } elseif (is_array($result)) {
            $first = true;

            $output[] = '[';
            foreach ($result as $v) {
                if ($first) {
                    $first = false;
                } else {
                    $output[] = ',';
                }

                self::jsonPrint($v, $externFunc, $output);
            }
            $output[] = ']';
        } else {
            $output[] = json_encode($result);
        }
    }
}

?>
