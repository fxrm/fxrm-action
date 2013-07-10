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

    function __construct(\Fxrm\Action\Form $form, $initialValueMap = array()) {
        $this->form = $form;
        $this->initialValueMap = $initialValueMap;
    }

    function url() {
        return $this->form->getUrl();
    }

    function formSuccess() {
        return $this->form->getSuccessStatus() ? (object)array('value' => $this->form->getSuccessValue()) : null;
    }

    function formError() {
        return $this->form->getError();
    }

    function __isset($fieldName) {
        return $this->form->getFieldExists($fieldName);
    }

    function __get($fieldName) {
        if ( ! $this->form->getFieldExists($fieldName)) {
            throw new \Exception('unknown field ' . $fieldName);
        }

        $field = (object)array(
            'labelHtml' => null,
            'inputName' => $fieldName,
            'inputNamePrivate' => "$fieldName\$",
            'inputValue' => $this->form->getFieldValue($fieldName, isset($this->initialValueMap[$fieldName]) ? $this->initialValueMap[$fieldName] : null),
            'error' => $this->form->getFieldError($fieldName)
        );

        $field->label = function ($text, $helper) use($field) {
            $field->labelHtml = $helper->render($text);
        };

        return $field;
    }
}

?>
