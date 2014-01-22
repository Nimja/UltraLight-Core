<?php
namespace Core\Form;
/**
 * Validator for values, based on a validation array.
 */
class Validate
{
    /**
     * Warnings as a result of the validation.
     * @var array
     */
    public $warnings = array();
    /**
     * The country-code for certain validations.
     * @var type
     */
    private $_countryCode = 'nl';
    /**
     * The current values we're validating.
     * @var array
     */
    private $_values;

    /**
     * validate request content with an array
     *
     * @param array $values The values to validate.
     * @param array $rules Array, used for validation
     *
     * Code [| min length ] [| label ]<br />
     * empty = Cannot be empty<br />
     * email = valid e-mail<br />
     * numeric = Numeric<br />
     * postal = Postal code (country specific)<br />
     * selected = At least one selected<br />
     * alpha = Alpha only, at least 2 letters.<br />
     * username = Letters, numbers and underscores.<br />
     * ignore = Will not count in validation.<br />
     * other = For dropdowns/selections with an 'other' option. Will check $field_other for the value.
     *
     * @param string $countrycode ISO Country code (for postal code checking, only NL/BE supported so far)
     *
     */
    public function validate($values, $rules, $countryCode = 'nl')
    {
        $this->_countryCode = $countryCode;
        $this->_values = $values;
        $this->warnings = array();
        if (empty($values) || empty($rules)) {
            return true;
        }
        foreach ($rules as $field => $rule) {
            $value = getKey($values, $field);
            $error = $this->_validate($field, $rule, $value);
            if (!empty($error)) {
                $this->warnings[$field] = $error;
            }
        }
        return empty($this->warnings);
    }

    /**
     * Validate a single field.
     * @param string $field
     * @param array $rule
     * @param mixed $value
     * @return string Error.
     */
    private function _validate($field, $rule, $value)
    {
        $parts = explode('|', $rule);
        $type = strtolower(array_shift($parts));
        //Find label and/or min-length.
        $minlen = 0;
        $label = null;
        if (!empty($parts)) {
            $label = array_shift($parts);
            if (is_numeric($label)) {
                $minlen = $label;
                $label = !empty($parts) ? array_shift($parts) : null;
            }
        }
        if (empty($label)) {
            $label = ucfirst($field);
        }
        $error = null;
        //Check minimum length.
        if ($minlen > strlen($value)) {
            $error = 'requires at least ' . $minlen . ' characters';
        } else {
            $error = $this->_validateType($type, $value, $field);
        }
        return $error;
    }
    /**
     * Validate for type.
     * @param string $type
     * @param mixed $value
     * @return string
     */
    private function _validateType($type, $value, $field)
    {
        $result = null;
        switch ($type) {
            case 'alpha':
                $result = $this->_pregValidate('/^[a-z\ ]+$/i', $value, 'Can only be letters and single spaces'
                );
                break;
            case 'username':
                $result = $this->_pregValidate('/^[a-zA-Z0-9\_]+$/i', $value,
                    'can only be letters, numbers or underscores.'
                );
                break;
            case 'other':
                if (empty($value) || ($value == 'other' && empty($this->_values[$field . '_other']) )) {
                    $result = 'has not been filled in';
                }
            case 'selected': #At least one selected
                $found = false;
                $len = strlen($field);
                foreach ($this->_values as $key => $value) {
                    if (substr($key, 0, $len) == $field && !empty($value)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result = 'has no selection';
                }
                break;
            case 'postal':
                $result = $this->_postalCheck($value);
                break;
            case 'numeric':
                $result = $this->_filterVar($value, FILTER_VALIDATE_INT, 'does not contain a valid number');
                break;
            case 'email': #email
                $result = $this->_filterVar($value, FILTER_VALIDATE_EMAIL, 'does not contain a valid email address');
                break;
            case 'other_ignore':
            case 'ignore': break;
            default: #not empty
                if (empty($value)) {
                    $result = 'is not filled in';
                }
                break;
        }
        return $result;
    }

    /**
     * Validate with regular expression.
     * @param string $preg
     * @param string  $value
     * @param string  $error
     * @return string|null
     */
    private function _pregValidate($preg, $value, $error)
    {
        $result = null;
        if (!preg_match($preg, $value)) {
            $result = $error;
        }
        return $result;
    }
    /**
     * Validate with php filter.
     * @param string $value
     * @param string $filter
     * @param string $error
     * @return string|null
     */
    private function _filterVar($value, $filter, $error) {
        $result = null;
        if (!filter_var($value, $filter)) {
            $result = $error;
        }
        return $result;
    }

    /**
     * Simple postal code check.
     * @param string $value
     * @return string
     */
    private function _postalCheck($value)
    {
        $regex = null;
        switch ($this->_countryCode) {
            case 'nl':
                $regex = '/^[0-9]{4,4} {0,1}[a-z]{2,2}$/i';
                break;
            case 'be':
                $regex = '/^[0-9]{4,4}$/';
                break;
            default:
                $regex = '/^[0-9]+$/i';
        }
        return $this->_pregValidate($regex, $value, 'is not valid for this country');
    }
}