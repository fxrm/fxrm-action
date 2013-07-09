<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Mustache;

/**
 * Mustache helper for form instances.
 */
class FormHelper {
    private $form;
    private $initialValueMap;

    private static $PRIVATE_SUFFIX = ':private';

    function __construct(\Fxrm\Action\Form $form, $initialValueMap = array()) {
        $this->form = $form;
        $this->initialValueMap = $initialValueMap;
    }

    function url() {
        return $this->form->getUrl();
    }

    function success() {
        return $this->form->getSuccessStatus() ? (object)array('value' => $this->form->getSuccessValue()) : null;
    }

    function error() {
        return $this->form->getError();
    }

    function __isset($fieldName) {
        $isPrivate = substr($fieldName, -strlen(self::$PRIVATE_SUFFIX)) === self::$PRIVATE_SUFFIX;

        if ($isPrivate) {
            $fieldName = substr($fieldName, 0, -strlen(self::$PRIVATE_SUFFIX));
        }

        return $this->form->getFieldExists($fieldName);
    }

    function __get($fieldName) {
        $isPrivate = substr($fieldName, -strlen(self::$PRIVATE_SUFFIX)) === self::$PRIVATE_SUFFIX;

        if ($isPrivate) {
            $fieldName = substr($fieldName, 0, -strlen(self::$PRIVATE_SUFFIX));
        }

        if ( ! $this->form->getFieldExists($fieldName)) {
            throw new \Exception('unknown field ' . $fieldName);
        }

        return (object)array(
            'inputName' => $isPrivate ? "$fieldName\$" : $fieldName,
            'inputValue' => $this->form->getFieldValue($fieldName, isset($this->initialValueMap[$fieldName]) ? $this->initialValueMap[$fieldName] : null),
            'error' => $this->form->getFieldError($fieldName)
        );
    }
}

?>
