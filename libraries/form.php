<?php

/*
 * Very complete form tool, allows for multiple fields in one block and optional
 * Select dropdowns. Very useful!
 * This is V3, not compatible with Form/form2 in home or Formlib
 * Almsot all configuration is done by arrays.
 */

class Form {

	/**
	 * Normally the HTML request variables.
	 * 
	 * @var array 
	 */
	public $request = array(); #Sanitized request values.
	protected $values = array();

	/**
	 * plain to proper lookup.
	 * 
	 * @var array 
	 */
	public $orivalues = NULL;

	/**
	 * Output string.
	 * 
	 * @var string 
	 */
	public $output = '';

	/**
	 * plain to proper lookup.
	 * 
	 * @var string 
	 */
	public $warning = '';

	/**
	 * Array of warnings.
	 * 
	 * @var array 
	 */
	public $warnings = array();

	/**
	 * If the form is valid, based on a validation array.
	 * 
	 * @var boolean 
	 */
	public $valid = FALSE;

	/**
	 * If we have an open fieldset
	 * 
	 * @var boolean 
	 */
	protected $openset = FALSE;

	function __construct()
	{

		$result = array();

		foreach ($_POST as $name => $value) {
			$value = sanitize($value);

			#Add the value.
			if (!blank($value))
				$result[$name] = $value;
		}
		foreach ($_GET as $name => $value) {
			#Skip values we have.
			if (isset($result[$name]))
				continue;

			$value = sanitize($value);

			#Add the value.
			if (!blank($value))
				$result[$name] = $value;
		}
		$this->request = $result;
	}

	/**
	 * Add something to the output
	 * 
	 * @param string $value
	 */
	public function add($value)
	{
		$this->output .= trim($value) . "\n";
	}

	/**
	 * Return current value, overwritten by "extra['value']"
	 * 
	 * @param string $value
	 * 
	 */
	public function value($field, $extra = array(), $escape = TRUE)
	{
		#Make sure the value is here.
		if (!isset($this->values[$field])) {
			#Forced var fill in. $extra['value']
			if (isset($extra['value'])) {
				$this->values[$field] = $extra['value'];

				#By request
			} else {
				$req = &$this->request;
				if (isset($req[$field])) {
					#Value fromt he request
					$val = $req[$field];
				} else {
					#Check if we have a database value for this.
					$data = & $this->orivalues;
					$val = (!empty($data) && !empty($data[$field])) ? $data[$field] : '';
				}
				$this->values[$field] = $val;
			}
		}

		#Return value.
		$value = $this->values[$field];
		if ($escape)
			$value = addcslashes($value, '"');
		return $value;
	}

	/**
	 * Add class to extra variable, without overwriting existing classes.
	 * 
	 * @param array $extra
	 * @param string $class
	 */
	function add_class(&$extra, $class)
	{
		if (!is_array($extra))
			$extra = array('extra' => $extra);

		$extra['class'] = !empty($extra['class']) ? $extra['class'] . ' ' . $class : $class;
	}

	/**
	 * Add class to extra variable, without overwriting existing classes.
	 * 
	 * @param array $val
	 * @param string $field
	 * @return string Extra details for fields.
	 */
	public function extra($val, $field = NULL)
	{
		$result = '';
		if (!empty($field)) {
			#There is an error on this field.
			if (!empty($this->warnings[$field])) {
				$this->add_class($val, 'error');
			}
		}
		if (!empty($val)) {

			if (!is_array($val)) {
				$result = ' ' . trim($val) . ' ';
			} else {
				#Parse over standard values.
				$parts = array();
				$fields = array('class', 'style', 'id', 'onchange', 'alt', 'title', 'for');
				foreach ($fields as $field) {
					if (!empty($val[$field]))
						$parts[] = $field . '="' . trim($val[$field]) . '"';
				}
				#Add optional extra 
				if (!empty($val['extra']))
					$parts[] = trim($val[$field]);

				#Join them together.
				$result = ' ' . implode(' ', $parts) . ' ';
			}
		}
		return $result;
	}

	/**
	 * Return an HTML form to edit this object.
	 * 
	 * @param string $page	Submit page.
	 * @param string $method post or get
	 * @param array $extra
	 * @return string Form HTML output.
	 */
	public function begin($page = '', $method = 'post', $extra = array())
	{
		$this->output = '';
		$this->add('<form action="' . $page . '" method="' . $method . '" ' . $this->extra($extra) . '>');
	}

	/**
	 * Close the form.
	 * 
	 */
	public function end()
	{
		$this->fieldset_close();
		$this->add('</form>');
	}

	/**
	 * Add class to extra variable, without overwriting existing classes.
	 * 
	 * @param array $val
	 * @param string $field
	 * @return string Extra details for fields.
	 */
	protected function field_input($type, $field, $extra = array())
	{
		$value = $this->value($field, $extra);
		if (!empty($extra['alt'])) {
			#Add faded class.
			$this->add_class($extra, 'faded');
			if (strtolower($value) == strtolower($extra['alt']))
				$value = '';
		}
		return '<input type="' . $type . '" name="' . $field . '" value="' . $value . '"' . $this->extra($extra, $field) . ' />';
	}

	//Making an input field.
	protected function field_text($field, $extra = array())
	{
		$value = $this->value($field, $extra, FALSE);
		return '<textarea name="' . $field . '"' . $this->extra($extra, $field) . '>' . $value . '</textarea>';
	}

	#Basic dropdown select.

	protected function field_select($field, $extra = array())
	{
		$options = '';
		$result_add = '';
		$cur = $this->value($field, $extra, FALSE);

		#Generate the options.
		$values = !empty($extra['values']) ? $extra['values'] : array();
		if (!empty($extra['other'])) {
			$values['other'] = $extra['other'];
			$this->add_class($extra, 'hasother');
			#Create hidden input field.
			$efield = $field . '_other';
			$value = $this->value($efield, $extra);
			if (empty($value) && $cur == 'other')
				$cur = '';
			$result_add = '<input type="hidden" name="' . $efield . '" value="' . $value . '" />';
		}

		foreach ($values as $index => $value) {
			$selected = ($cur == $index) ? ' selected = "selected" ' : '';
			$options .= '<option value="' . $index . '"' . $selected . '>' . $value . '</option>';
		}
		$result = '<select name="' . $field . '"' . $this->extra($extra, $field) . '>' . $options . '</select>';

		return $result . $result_add;
	}

	#Multiple select field, selection is an array.

	protected function field_multiselect($field, $extra = array())
	{
		$cur = $this->value($field, $extra, FALSE);
		if (!is_array($cur))
			$cur = array($cur);

		$options = '';
		$values = !empty($extra['values']) ? $extra['values'] : array();

		foreach ($values as $index => $value) {
			if (!empty($index)) {
				$selected = (in_array($index, $cur)) ? ' selected = "selected" ' : '';
				$options .= '<option value="' . $index . '"' . $selected . '>' . $value . '</option>';
			}
		}
		$result = '<select multiple="multiple" name="' . $field . '[]"' . $this->extra($extra, $field) . '>' . $options . '</select>';
		return $result;
	}

	//Making a number of radio fields
	protected function field_radio($field, $extra = array())
	{
		$result = '';
		$value = $this->value($field, $extra, FALSE);
		$values = !empty($extra['values']) ? $extra['values'] : array();

		$extra = $this->extra($extra, $field);
		foreach ($values as $index => $value) {
			$selected = ($value == $index) ? ' checked = "checked" ' : '';
			$result .= '<span><input type="radio" name="' . $field . '" value="' . $index . '" ' . $selected . $extra . '>' . $value . '</span>';
		}
		return $result;
	}

	//Making a select field (with array as input, re-selects right value)
	protected function field_check($field, $extra = NULL)
	{
		$value = $this->value($field, $extra);
		$selected = (!empty($value) && $value != 'false') ? ' checked="checked" ' : '';
		$result = '<input type="checkbox" name="' . $field . '" value="yes"' . $selected . '' . $this->extra($extra, $field) . '/>';
		if (!empty($extra['label'])) {
			$result .= '<span>' . $extra['label'] . '</span>';
		}
		return $result;
	}

	#Envelop relevant fields in a fieldset is good practice.

	public function fieldset($legend = NULL, $extra = array())
	{
		$this->fieldset_close();
		$this->add('<fieldset' . $this->extra($extra) . '>');
		if (!empty($legend))
			$this->add('<legend>' . $legend . '</legend>');
		$this->openset = TRUE;
	}

	#Close the currently open fieldset.

	public function fieldset_close()
	{
		if ($this->openset)
			$this->add('<div class="clear"></div></fieldset>');
		$this->openset = FALSE;
	}

	#Basic field selection.

	public function field_make($type, $field, $extra = array())
	{
		$result = '';
		switch ($type) {
			case 'multiple': $result = $this->field_multiselect($field, $extra);
				break;
			case 'select': $result = $this->field_select($field, $extra);
				break;

			case 'radio': $result = $this->field_radio($field, $extra);
				break;

			case 'check': $result = $this->field_check($field, $extra);
				break;
			case 'text': $result = $this->field_text($field, $extra);
				break;

			#Basic input, also implements password and submit.
			default: $result = $this->field_input($type, $field, $extra);
				break;
		}
		return $result;
	}

	/**
	 * Add a field to the form.
	 * 
	 * @param array $type Input, submit, select, etc.Ï
	 * @param string $field The Name of this field
	 * @param string $label
	 * @param array $extra
	 * @param boolean $direct Add output, or return output.
	 */
	public function field($type, $field, $label = NULL, $extra = array(), $direct = FALSE)
	{
		$result = $this->field_make($type, $field, $extra);

		#Envelop field, except when it's hidden.
		if ($type != 'hidden') {
			$label = empty($label) ? '' : '<label for="' . $field . '">' . $label . '</label>';
			$lead = !empty($extra['lead']) ? $extra['lead'] : '';
			$trail = !empty($extra['trail']) ? $extra['trail'] : '';
			$result = '<div class="field ' . $type . '">' . $lead . $label . $result . $trail . '</div>';
		}
		//Output result or add it to the form.
		if (!$direct) {
			$this->add($result);
			return TRUE;
		} else {
			return $result;
		}
	}

	/**
	 * Add multiple fields with one enveloping label, like street + number
	 * 
	 * @param array $fields
	 * @param string $label
	 * @param boolean $direct Add output, or return output.
	 */
	public function field_multi($fields, $label = NULL, $direct = FALSE)
	{
		$result = '';
		$classes = array();
		foreach ($fields as $field => $extra) {
			if (!empty($extra['type'])) {
				$classes[$extra['type']] = $extra['type'];
				$result .= $this->field_make($extra['type'], $field, $extra);
			}
		}

		#Envelop field
		$label = empty($label) ? '' : '<label for="' . $field . '">' . $label . '</label>';
		$trail = !empty($extra['trail']) ? $extra['trail'] : '';
		$class = !empty($classes) ? ' ' . implode(' ', $classes) : '';
		$result = '<div class="field' . $class . '">' . $label . $result . $trail . '</div>';

		//Output result or add it to the form.
		if (!$direct) {
			$this->add($result);
			return TRUE;
		} else {
			return $result;
		}
	}

	/**
	 * validate request content with an array
	 * 
	 * @param array $validate Array, used for validation
	 * 
	 * Code [| min length ] [| label ]<br />
	 * empty = Cannot be empty<br />
	 * email = valid e-mail<br />
	 * numeric = Numeric<br />
	 * postal = Postal code (country specific)<br />
	 * selected = At least one selected<br />
	 * alpha = Alpha only, at least 2 letters.<br />
	 * ignore = Will not count in validation.
	 * 
	 * @param string $countrycode ISO Country code (for postal code checking, only NL/BE supported so far)
	 * 
	 */
	public function validate($validate, $countrycode = 'nl')
	{
		$result = FALSE;
		#---validation
		$this->warning = '';
		$this->warnings = array();

		if (!empty($validate)) {
			$post = &$this->request;

			$total = count($validate);
			$checked = 0;
			foreach ($validate as $field => $value) {
				$parts = explode('|', $value);
				$type = strtolower(array_shift($parts));

				#how to name this field
				$minlen = 0;
				if (!empty($parts)) {
					$label = array_shift($parts);
					if (is_numeric($label)) {
						$minlen = $label;
						$label = (!empty($parts)) ? array_shift($parts) : ucfirst($field);
					}
				} else {
					$label = (!empty($parts)) ? array_shift($parts) : ucfirst($field);
				}


				$value = (!empty($post[$field]) || (isset($post[$field]) && $post[$field] == '0')) ? trim($post[$field]) : '';

				if (empty($value))
					$checked++;

				$text = '';
				$length = $minlen . ' - ' . strlen($value);

				#if length check fails
				if ($minlen > strlen($value)) {
					$text = 'requires at least ' . $minlen . ' characters';
				} else {
					switch ($type) {
						case 'alpha': #Alpha only
							if (!preg_match('/^[a-z\ ]+$/i', $value))
								$text = 'Can only be letters and single spaces';
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
					$this->warnings[$field] = '<p><b>' . $label . '</b> ' . $text . '.</p>';
				}
			}
			$this->warning = !empty($this->warnings) ? implode('', $this->warnings) : '';
			$result = empty($this->warning);
			#If ALL fields are wrong, don't provide warnings.
			if ($checked == $total) {
				#$this->warning = '';
				#$this->warnings = array();
			}
		}
		return $result;
	}

	/**
	 * Add class to extra variable, without overwriting existing classes.
	 * 
	 * @param string $email
	 * @return boolean True for valid e-mail address.
	 */
	protected function valid_email($email)
	{
		return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email)) ? FALSE : TRUE;
	}

}