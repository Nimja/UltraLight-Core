<?php

Load::library('database');

/* - This is a simple class-holder for a model, using a DB connection.
 * Containing many useful functions for insert, delete, etc. 
 * This is one of the few libraries using another library as a dependancy.
 * Things needing defining:
 * $_fields array, which defines the table and the used fields.
 * $_table, defining the table name.
 */

abstract class Model
{
	#Every model shares the same connection. (unless overridden of course)

	/**
	 * Static database object.
	 * 
	 * @var DataBase 
	 */
	private static $db;

	/**
	 * Static database object.
	 * 
	 * @var DataBase 
	 */
	private static $prefix = null;

	/**
	 * plain to proper lookup.
	 * 
	 * @var array 
	 */
	private static $loadedClasses = array();

	/**
	 * Table for the model
	 * 
	 * @var string 
	 */
	protected $_table = '';

	/**
	 * Field used for ordering and listing.
	 * 
	 * @var string 
	 */
	protected $_listField = 'id';

	/**
	 * Database object for this model.
	 * 
	 * @var DataBase 
	 */
	protected $_db = null;

	/**
	 * Database fields
	 * 
	 * @var array 
	 */
	protected $_fields = array();

	/**
	 * Fields to ignore when saving or making the form. They are usually saved in a different method (like sorting)
	 * 
	 * Filled automatically.
	 * 
	 * @var array 
	 */
	protected $_ignoreFields = array();

	/**
	 * Using the form validation function, if you need to.
	 * 
	 * Filled automatically.
	 * 
	 * @var array 
	 */
	protected $_validate = array();

	/**
	 * ID variable
	 * 
	 * @var int 
	 */
	public $id = 0;

	/**
	 * The classname for this object.
	 * 
	 * @var string 
	 */
	protected $_class = null;

	/**
	 * Holder for the form object connected to this function.
	 * 
	 * @var Form 
	 */
	protected $_form = null;

	/**
	 * Internal memory caching for models.
	 * 
	 * @var array 
	 */
	protected $_cache = null;

	/**
	 * Static memory caching.
	 * @var array 
	 */
	protected static $cache = array();

	/**
	 * Flag for editing.
	 * 
	 * @var boolean 
	 */
	protected $_edit = false;

	/**
	 * Is true if a save succeeded succesfully.
	 * 
	 * @var type 
	 */
	protected $_saved = false;

	/**
	 * Basic constructor, with error messages.
	 * 
	 * @param array|int $values 
	 */
	function __construct($values = null)
	{
		$this->_class = get_class($this);

		if (class_exists('Login')) {
			$this->_edit = Login::$role > Login::ROLE_NEUTRAL;
		}

		//Create the validation/ignore arrays for ease of use.
		foreach ($this->_fields as $field => $settings) {
			if (isset($settings['validate'])) {
				$this->_validate[$field] = $settings['validate'];
			}
			if (!empty($settings['ignore'])) {
				$this->_ignoreFields[$field] = true;
			}
		}

		#Get cache, or make one for this class if it didn't exist.
		if (!isset(self::$cache[$this->_class])) {
			self::$cache[$this->_class] = array();
		}
		#Set the cache.
		$this->_cache = &self::$cache[$this->_class];

		if (empty($this->_fields) || !is_array($this->_fields))
			show_exit('Cannot create model unless fields is defined.', $this->_class);

		$info = Load::getNames($this->_class);
		if (self::$prefix === null) {
			self::getPrefix();
		}
		$this->_table = self::$prefix . $info['file'];

		#Set the values, either by ID (int) or values(array);
		if (is_numeric($values)) {
			$this->load(intval($values));
		} else if (is_array($values)) {
			$this->fill($values);
		}
	}

	public function __toString()
	{
		return show($this, $this->_class, '#efe', true);
	}

	/**
	 * Return an array of default values.
	 * @return array Default values for this class.
	 */
	protected function getDefaults()
	{
		Load::library('defaults');
		$function = 'for' . $this->_class;
		return Defaults::$function();
	}

	/**
	 * Connect to the database for this model, only happens when actual connections are needed.
	 * 
	 * @return boolean true for connection.
	 */
	protected function connect()
	{
		if (!empty($this->_db))
			return true;

		if (!empty(self::$db)) {
			$this->_db = &self::$db;
			return true;
		}

		if (empty($this->_table))
			show_exit('Cannot connect to database unless table is defined.', $this->_class);
		if (empty($this->_fields) || !is_array($this->_fields))
			show_exit('Cannot connect to database unless fields is defined.', $this->_class);

		self::$db = new DataBase();
		$this->_db = self::$db;
	}

	/**
	 * Check this model, only checking if the table exists.
	 * 
	 * @return boolean Table exists or not.
	 */
	protected function check()
	{
		if (empty($this->_table))
			return false;

		if (empty($this->_db))
			$this->connect();

		return $this->_db->table_exists($this->_table);
	}

	/**
	 * Install this model, ie. create the table if it is required.
	 * 
	 * @return type 
	 */
	public function install($force = false)
	{
		$fields = $this->_fields;

		if (empty($fields))
			return false;

		if (empty($this->_db))
			$this->connect();

		if (!$this->check() || $force) {
			$installed = $this->_db->create_table($this->_table, $fields, true);

			#Install default values, if needs be.
			$defaults = $this->getDefaults();
			if (!empty($defaults)) {
				$this->addMultiple($defaults);
			}
			if ($installed) {
				return show($this->_class, 'Installed', 'success', true);
			} else {
				return show($this->_class, 'Not installed!', 'error', true);
			}
		} else {
			$updated = $this->_db->update_table($this->_table, $fields);
			if ($updated) {
				return show($this->_class, 'Updated', 'good', true);
			} else {
				return show($this->_class, 'No changes.', 'neutral', true);
			}
		}
	}

	/**
	 * Create multiple items at once with associative arrays.
	 * 
	 * @param array $array
	 * @return type 
	 */
	public function addMultiple($array)
	{
		if (!is_array($array))
			return false;

		#Look over array and add them.
		foreach ($array as $item) {
			if (is_array($item)) {
				$current = new $this->_class($item);
				$current->save();
			}
		}
	}

	/**
	 * Fill the object, empty means everything is blank.
	 * 
	 * @var array $values 
	 * @return array All the values of this object.
	 */
	public function fill($values)
	{
		if (empty($values) || !is_array($values))
			return false;

		foreach ($this->_fields as $field => $setting) {
			if ($setting['type'] == 'bool') {
				$this->$field = !empty($values[$field]) ? 1 : 0;
			} else {
				$this->$field = !blank($values[$field]) ? $values[$field] : '';
			}
		}

		if (!empty($values['id'])) {
			$this->id = intval($values['id']);
			$this->_cache[$this->_class . '-' . $this->id] = $this;
		}
	}

	protected function fromCache($id)
	{
		if (empty($this->_cache[$this->_class . '-' . $id])) {
			return false;
		}
		$item = $this->_cache[$this->_class . '-' . $id];
		foreach ($this->_fields as $field => $type) {
			$this->$field = $item->$field;
		}
		$this->id = $item->id;
		return true;
	}

	/**
	 * Retrieve all the values as a associative array.
	 * 
	 * @return array All the values of this object.
	 */
	public function values()
	{
		$result = array();
		foreach ($this->_fields as $field => $type) {
			if (!blank($this->$field))
				$result[$field] = $this->$field;
		}

		$result['id'] = $this->id;
		return $result;
	}

	/**
	 * Save the current object to the DB, as new or update.
	 * 
	 */
	public function save()
	{
		if (!$this->_edit)
			return;

		if (empty($this->_db))
			$this->connect();

		$values = $this->values();
		#DO we want to do a validation check?

		$id = intval($this->id);

		#Switch between update and insert automatically.
		if ($id > 0) {
			#Remove these fields from the values to be saved.
			if (!empty($this->_ignoreFields)) {
				foreach ($this->_ignoreFields as $field => $true) {
					unset($values[$field]);
				}
			}
			#Update the data in the database.
			$this->_db->update($this->_table, $values, 'id=' . $id);
		} else {
			$this->id = $this->_db->insert($this->_table, $values);
		}
		$this->_cache[$this->_class . '-' . $this->id] = $this;
		$this->_saved = true;
		return true;
	}

	/**
	 * Load this object by ID
	 * 
	 * @param int $id
	 * @return boolean Success
	 */
	protected function load($id)
	{
		$id = intval($id);

		if (empty($id))
			return false;

		if (empty($this->_db))
			$this->connect();

		$values = $this->_db->run('SELECT * FROM `' . $this->_table . '` WHERE id = ' . $id);

		if (empty($values) || empty($values['id'])) {
			#show_error($id, 'Failed to load ' . $this->_class);
			return false;
		}

		$this->fill($values);
		$this->id = $values['id'];
		return true;
	}

	/**
	 * Delete this object (or specific id)
	 * 
	 * @param optional int $id
	 */
	public function delete($id = null)
	{
		if (!$this->_edit)
			return;

		$id = !empty($id) ? $id : $this->id;
		$id = intval($id);

		if (empty($this->_db))
			$this->connect();

		$this->_db->delete($this->_table, $id);

		if ($this->id == $id)
			$this->id = 0;
	}

	/**
	 * Get a basic array of objects for this class.
	 * 
	 * @param string $where Basic SQL where structure.
	 * @param string $orderBy And order by.
	 * @return array Field structure.
	 */
	public function getAll($where = '', $orderBy = null, $limit = null)
	{
		if (empty($this->_db))
			$this->connect();

		$orderBy = !empty($orderBy) ? $orderBy : $this->_listField . ' ASC';


		$extra = '';
		if (!empty($where)) {
			$extra .= ' WHERE ' . $where;
		}
		if (!empty($orderBy)) {
			$extra .= ' ORDER BY ' . $orderBy;
		}
		if (!empty($limit)) {
			$extra .= ' LIMIT ' . $limit;
		}
		$res = $this->_db->query('SELECT * FROM `' . $this->_table . '` ' . $extra);
		$result = array();
		while ($row = $this->_db->getRow($res)) {
			$result[$row['id']] = new $this->_class($row);
		}
		return $result;
	}

	/**
	 * Get a single instance of this class, based on a search. 
	 * 
	 * @param string $where Basic SQL where structure.
	 * @param string $orderBy And order by.
	 * @return Model Object of an extended class.
	 */
	public function getOne($where = '', $orderBy = null)
	{
		if (empty($this->_db))
			$this->connect();

		$orderBy = !empty($orderBy) ? $orderBy : $this->_listField . ' ASC';

		$extra = '';
		if (!empty($where)) {
			$extra .= ' WHERE ' . $where;
		}
		if (!empty($orderBy)) {
			$extra .= ' ORDER BY ' . $orderBy;
		}
		$row = $this->_db->run('SELECT * FROM `' . $this->_table . '` ' . $extra);

		return!empty($row) ? new $this->_class($row) : false;
	}

	/**
	 * Get a basic array list for this object, for use in dropdowns.
	 * 
	 * @param string $where Basic SQL where structure.
	 * @param string $orderBy And order by.
	 * @return array Field structure.
	 */
	public function getSelectionList($where = '', $orderBy = null)
	{
		if (empty($this->_db))
			$this->connect();

		$orderBy = !empty($orderBy) ? $orderBy : $this->_listField . ' ASC';

		$extra = '';
		if (!empty($where)) {
			$extra .= ' WHERE ' . $where;
		}
		if (!empty($orderBy)) {
			$extra .= ' ORDER BY ' . $orderBy;
		}

		return $this->_db->getList('id', $this->_listField, 'SELECT * FROM `' . $this->_table . '` ' . $extra);
	}

	/**
	 * Return the field structure for this object.
	 * 
	 * @return array Field structure.
	 */
	public function getFields()
	{
		return $this->_fields;
	}

	/**
	 * Return the current table.
	 * 
	 * @return array Field structure.
	 */
	public function getTable()
	{
		return $this->_table;
	}

	/**
	 * Return an HTML form to edit this object.
	 * 
	 * @param string $page	Submit page.
	 * @param string $method post or get
	 * @param array $extra
	 * @return string Form HTML output.
	 */
	public function getForm($page = '', $method = 'post', $extra = array())
	{
		if (empty($this->_form)) {
			Load::library('form');
			$this->_form = new Form();
		}
		#Begin the form.
		$fields = $this->_fields;

		$form = $this->_form;

		$extra['class'] = 'editor';
		$form->begin($page, $method, $extra);

		#Set the original values.
		$form->orivalues = $this->values();

		#Basic fields.
		$form->field('hidden', 'class', null, array(
			'value' => strtolower($this->_class),
			'ref' => $this->_saved ? 'saved' : '',
		));

		if (!empty($form->warning)) {
			$form->add('<div class="warning">' . $form->warning . '</div>');
		}

		#Current object selection.
		$list = array('' => '--new--') + $this->getSelectionList();
		$form->field('select', 'id', 'Current', array('values' => $list, 'class' => 'idSelect'));

		$form->fieldset('Values', array('class' => 'values ' . strtolower($this->_class)));
		foreach ($fields as $field => $setting) {
			$this->getFormField($field, $setting);
		}
		$form->field('submit', 'submit', null, array('value' => 'Save', 'class' => 'save'));
		$form->field('submit', 'cancel', null, array('value' => 'Cancel', 'class' => 'cancel'));

		#Execute extra details for a form.
		$this->getFormExtra($form);

		$form->end();

		return $form->output;
	}

	/**
	 * Create (or ignore) a field for this form.
	 * 
	 * @param Form $form
	 * @param string $field
	 * @param array $setting
	 */
	public function getFormField($field, $setting)
	{
		#Ignore these fields for editing.
		if (!empty($this->_ignoreFields[$field]))
			return;

		$form = $this->_form;

		# We only need the type, not anything else.
		$type = $setting['type'];
		$lastChars = substr($field, -2);

		$ttype = substr($type, -3);

		switch ($ttype) {
			#Text types.
			case 'ext':
				$form->field('text', $field, ucfirst($field));
				break;

			#Bool
			case 'ool':
				$form->field('check', $field, '&nbsp;', array('label' => ucfirst($field)));
				break;
			#Numeric, id or date types
			case 'int':
				if ($lastChars == 'On') {
					$form->orivalues[$field] = date('d-m-Y H:i:s', $this->$field);

					$form->field('input', $field, ucfirst($field), array('class' => 'dateField'));
				} else if ($lastChars == 'Id') {
					$fieldClass = substr($field, 0, -2);

					#If we're looking for parents, using currnet class.
					if ($fieldClass == 'parent')
						$fieldClass = strtolower($this->_class);

					$list = self::make($fieldClass);

					$ref = $fieldClass . '-' . intval($this->$field);

					$extra = array('values' => $list->getSelectionList(), 'lead' => '<span class="edit" ref="' . $ref . '"></span>');

					$form->field('select', $field, ucfirst(substr($field, 0, -2)), $extra);
				} else {
					$form->field('input', $field, ucfirst($field), array('class' => 'numberField'));
				}

				break;
			default: $form->field('input', $field, ucfirst($field), array('class' => $type));
		}
	}

	/**
	 * Do additional stuff for a form.
	 */
	protected function getFormExtra()
	{
		//Placeholder function.
	}

	/**
	 * Validate the array, using the form rules.
	 */
	public function validateRequest()
	{
		#No validation rules, always true.
		if (empty($this->_validate))
			return true;

		#Run the form validation.
		if (empty($this->_form)) {
			Load::library('form');
			$this->_form = new Form();
		}
		$form = $this->_form;
		return $form->validate($this->_validate);
	}

	public function editTag($class = '', $parentId = 0)
	{
		if (!$this->_edit)
			return '';

		$thisClass = strtolower($this->_class);

		if (!empty($class) && !empty($parentId)) {
			return '<span class="edit edit_new" ref="' . $class . '--' . $parentId . '">New ' . ucfirst($class) . '</span>';
		} else {
			return '<span class="edit" ref="' . $thisClass . '-' . $this->id . '"></span>';
		}
	}

	/**
	 *
	 * @param type $alias
	 * @param string $alt Alternative for alias (like a title)
	 * @return string Properly formatted alias. 
	 */
	protected function validAlias($alias, $alt)
	{
		#Clean Alias.
		if (blank($alias)) {
			$alias = preg_replace('/\s+/', ' ', trim($alt));
		}
		#Clean the alias.
		$alias = strtolower(trim($alias));
		$alias = str_replace(' ', '_', $alias);
		$alias = preg_replace('/[^a-z0-9\-\_\/\.]/', '', $alias);
		return $alias;
	}

	/* ------------------------------------------------------------
	 * 			STATIC FUNCTIONS
	 * ------------------------------------------------------------
	 */

	/**
	 * Get a basic array of objects for this class.
	 * 
	 * @param string $where Basic SQL where structure.
	 * @param string $orderBy And order by.
	 * @return array Field structure.
	 */
	public static function getModels($type, $where = '', $orderBy = null, $limit = null)
	{
		$model = self::make($type);
		return!empty($model) ? $model->getAll($where, $orderBy, $limit) : false;
	}

	/**
	 * Load multiple models at once.
	 * @param string|array $classNames 
	 */
	public static function loadModels($classNames)
	{
		if (!is_array($classNames)) {
			$classNames = explode(',', $classNames);
		}
		foreach ($classNames as $className) {
			$className = trim($className);
			self::loadModel($className);
		}
	}

	/**
	 * Load model file for this class.
	 * @param string $className 
	 */
	public static function loadModel($type)
	{
		$info = Load::getNames($type);
		$file = $info['file'];
		$class = $info['class'];

		if (!empty(self::$loadedClasses[$file])) {
			return self::$loadedClasses[$file];
		}

		#Load the model if needs be.
		if (!class_exists($class))
			Load::library($file, 'model');

		self::$loadedClasses[$file] = $class;

		#Return the real classname.
		return $class;
	}

	/**
	 * Create an object of model Class, with validation so 'wrong' objects cannot be created.
	 * 
	 * @param string $class
	 * @param array|int $values
	 * @return Model A class, derived from model, based on $class.
	 */
	public static function make($type, $values = null, $force = false)
	{
		if (empty($type))
			return null;
		$class = self::loadModel($type);

		if (!$force
				&& !empty(self::$cache[$class])
				&& !empty(self::$cache[$class][$class . '-' . $values])
		) {
			#Return the cached object.
			return self::$cache[$class][$class . '-' . $values];
		} else {
			#Return a new object.
			return new $class($values);
		}
	}

	public static function getPrefix()
	{
		self::$prefix = !empty($GLOBALS['config']['table_prefix']) ? $GLOBALS['config']['table_prefix'] : '';
	}

}