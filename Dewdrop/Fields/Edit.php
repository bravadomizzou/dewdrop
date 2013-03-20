<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Fields;

use Dewdrop\Db\Field;
use Dewdrop\Exception;
use Zend\InputFilter\InputFilter;

/**
 * Use the \Dewdrop\Db\Field API to manage the editing of values.
 */
class Edit
{
    /**
     * The collection of fields added to this object
     *
     * @var array
     */
    private $fields = array();

    /**
     * Object used to filter and validate user input
     *
     * @var \Zend\InputFilter\InputFilter
     */
    private $inputFilter;

    /**
     * Create InputFilter instance for validation and filtering of input
     *
     * @param InputFilter $inputFilter
     */
    public function __construct(InputFilter $inputFilter = null)
    {
        $this->inputFilter = ($inputFilter ?: new InputFilter());
    }

    /**
     * Add a field, optionally changing its control name to disambiguate it
     * from other fields with the same control name on this page.
     *
     * @param Field $field
     * @param string $groupName
     * @return \Dewdrop\Fields\Edit
     */
    public function add(Field $field, $groupName = null)
    {
        if (null === $groupName) {
            $this->fields[$field->getControlName()] = $field;
        } else {
            $fieldIndex = $groupName . ':' . $field->getName();
            $field->setControlName($fieldIndex);
            $this->fields[$fieldIndex] = $field;
        }

        return $this;
    }

    /**
     * Get the field matching the supplied control name.
     *
     * @throws \Dewdrop\Exception
     * @param string $controlName
     * @return \Dewdrop\Db\Field
     */
    public function get($controlName)
    {
        if (!$this->has($controlName)) {
            throw new Exception("Unknown field \"{$controlName}\" requested");
        }

        return $this->fields[$controlName];
    }

    /**
     * Check to see if this object has a reference to the field with the
     * provided control name.
     *
     * @param string $controlName
     * @return boolean
     */
    public function has($controlName)
    {
        return array_key_exists($controlName, $this->fields);
    }

    /**
     * Set values for any fields managed by this object with a control name
     * matching a key of the supplied $values array.
     *
     * This method is also responsible for transforming the input when
     * necessary for it to be useful for the database.  For example, when
     * unchecked, HTML inputs of type "checkbox" will not be present in
     * POST at all.  In that case, this method will detect that the POST
     * key is absent and set the assocaited field's value to zero.
     *
     * @param array $values
     * @return \Dewdrop\Fields\Edit
     */
    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            if ($this->has($key)) {
                $field = $this->get($key);

                // For some odd reason wp_editor() adds slashes by quotes.  Breaks
                // many things.  This works around that problem.
                if ($field->isType('text')) {
                    $value = stripslashes($value);
                }

                $field->setValue($value);
            }
        }

        // When not checked, checkboxes are excluded from POST in full.
        // This loop works around that quirk.
        foreach ($this->fields as $field) {
            if ($field->isType('tinyint') && !array_key_exists($field->getControlName(), $values)) {
                $field->setValue(0);
            }
        }

        return $this;
    }

    /**
     * Check to see if the supplied values are valid.  If you've already called
     * setValues(), those values will be used for validation as well.
     *
     * @param array $values
     * @return boolean
     */
    public function isValid(array $values = null)
    {
        foreach ($this->fields as $field) {
            $this->inputFilter->add($field->getInputFilter());
        }

        if (null !== $values) {
            $this->setValues($values);
        } else {
            $values = array();

            foreach ($this->fields as $field) {
                $values[$field->getControlName()] = $field->getValue();
            }
        }

        $this->inputFilter->setData($values);

        return $this->inputFilter->isValid();
    }

    /**
     * Get any validation messages that were generated when isValid() was
     * called.
     *
     * @return array
     */
    public function getMessages()
    {
        $messages = array();

        foreach ($this->inputFilter->getInvalidInput() as $id => $error) {
            foreach ($error->getMessages() as $message) {
                $messages[] = $this->get($id)->getLabel() . ': ' . $message;
            }
        }

        return $messages;
    }
}