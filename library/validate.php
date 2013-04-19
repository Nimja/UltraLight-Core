<?php

/**
 * Basic String to HTML formatting class.
 *
 */
class Library_Validate
{

    public $warnings = array();

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
     * ignore = Will not count in validation.
     *
     * @param string $countrycode ISO Country code (for postal code checking, only NL/BE supported so far)
     *
     */
    public function validate($values, $rules, $countrycode = 'nl')
    {
        $this->warnings = array();

        if (empty($values) || empty($rules)) {
            return true;
        }

        foreach ($rules as $field => $rule) {
            $parts = explode('|', $rule);
            $type = strtolower(array_shift($parts));

            #how to name this field
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

            $value = getKey($values, $field);
            $text = '';

            #if length check fails
            if ($minlen > strlen($value)) {
                $text = 'requires at least ' . $minlen . ' characters';
            } else {
                switch ($type) {
                    case 'alpha': #Alpha only
                        if (!preg_match('/^[a-z\ ]+$/i', $value))
                            $text = 'Can only be letters and single spaces';
                        break;
                    case 'username': #Username check.
                        if (!preg_match('/^[a-zA-Z0-9\_]+$/i', $value))
                            $text = 'You can only use letters, numbers and underscores. (case sensitive)';
                        break;
                    case 'other':
                        if (empty($value) || ($value == 'other' && empty($post[$field . '_other']) ))
                            $text = 'has not been filled in';
                    case 'selected': #At least one selected
                        $found = 0;
                        $len = strlen($field);
                        foreach ($post as $key => $value) {
                            if (substr($key, 0, $len) == $field && !empty($value)) {
                                $found++;
                            }
                        }
                        if ($found == 0)
                            $text = 'has no selection';
                        break;
                    case 'postal': #postal code
                        $regex = '/^[0-9]{4,4} {0,1}[a-z]{2,2}$/i';
                        if ($countrycode == 'be')
                            $regex = '/^[0-9]{4,4}$/';
                        if (!preg_match($regex, $value))
                            $text = 'is not valid for this country';
                        break;
                    case 'numeric': #numeric
                        if (!preg_match('/^[0-9]+$/', $value))
                            $text = 'does not contain a valid number';
                        break;
                    case 'email': #email
                        if (!$this->valid_email($value))
                            $text = 'does not contain a valid email address';
                        break;
                    case 'other_ignore':
                    case 'ignore': break;
                    default: #not empty
                        if (empty($value))
                            $text = 'is not filled in';
                        break;
                }
            }
            if (!empty($text)) {
                $this->warnings[$field] = $text;
            }
        }
        return empty($this->warnings);
    }

    /**
     * Check if string is a valid e-mail address.
     *
     * @param string $email
     * @return boolean True for valid e-mail address.
     */
    protected function valid_email($string)
    {
        return preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $string);
    }

}