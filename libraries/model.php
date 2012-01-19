<?php
Load::library('database,user');

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
	 * plain to proper lookup.
	 * 
	 * @var array 
	 */
	private static $classLookup;

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
	protected $_db = NULL;

	/**
	 * Database fields
	 * 
	 * @var array 
	 */
	protected $_fields = array();

	/**
	 * Fields to ignore when saving or making the form. They are usually saved in a different method (like sorting)
	 * 
	 * @var array 
	 */
	protected $_ignoreFields = array();

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
	protected $_class = NULL;

	/**
	 * Holder for the form object connected to this function.
	 * 
	 * @var Form 
	 */
	protected $_form = NULL;

	/**
	 * Using the form validation function, if you need to.
	 * 
	 * @var array 
	 */
	protected $_validate = array();
	protected static $cache = array();

	/**
	 * Internal caching.
	 * 
	 * @var array 
	 */
	protected $_cache = NULL;

	#Basic constructor, with error messages.

	/**
	 * Basic constructor.
	 * 
	 * @param array|int $values 
	 */
	function __construct($values = array())
	{
		$this->_class = get_class($this);

		#Get cache.
		if (empty(self::$cache[$this->_class]))
			self::$cache[$this->_class] = array();

		$this->_cache = &self::$cache[$this->_class];

		if (empty($this->_table))
			show_exit('Cannot create model unless table is defined.', $this->_class);
		if (empty($this->_fields) || !is_array($this->_fields))
			show_exit('Cannot create model unless fields is defined.', $this->_class);

		$config = &$GLOBALS['config'];
		$prefix = !empty($config['table_prefix']) ? $config['table_prefix'] : '';
		$this->_table = $prefix . $this->_table;

		#Set the values, either by ID (int) or values(array);
		if (is_numeric($values)) {
			$this->load(intval($values));
		} else if (is_array($values)) {
			$this->fill($values);
		}
	}

	public function __toString()
	{
		return show($this, $this->_class, '#efe', TRUE);
	}

	/**
	 * Return an array of default values.
	 * @return array Default values for this class.
	 */
	protected function getDefaults()
	{
		return NULL;
	}

	/**
	 * Connect to the database for this model, only happens when actual connections are needed.
	 * 
	 * @return boolean TRUE for connection.
	 */
	protected function connect()
	{
		if (!empty($this->_db))
			return TRUE;

		if (!empty(self::$db)) {
			$this->_db = &self::$db;
			return TRUE;
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
			return FALSE;

		if (empty($this->_db))
			$this->connect();

		return $this->_db->table_exists($this->_table);
	}

	/**
	 * Install this model, ie. create the table if it is required.
	 * 
	 * @return type 
	 */
	public function install($force = FALSE)
	{
		$fields = $this->_fields;

		if (empty($fields))
			return FALSE;

		if (empty($this->_db))
			$this->connect();

		if (!$this->check() || $force) {
			$installed = $this->_db->create_table($this->_table, $fields, TRUE);

			#Install default values, if needs be.
			$defaults = $this->getDefaults();
			if (!empty($defaults)) {
				$this->addMultiple($defaults);
			}
			if ($installed) {
				return show($this->_class, 'Installed', 'success', TRUE);
			} else {
				return show($this->_class, 'Not installed!', 'error', TRUE);
			}
		} else {
			$updated = $this->_db->update_table($this->_table, $fields);
			if ($updated) {
				return show($this->_class, 'Updated', 'good', TRUE);
			} else {
				return show($this->_class, 'No changes.', 'neutral', TRUE);
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
			return FALSE;

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
			return FALSE;

		if (!empty($values['id']))
			$this->id = intval($values['id']);

		foreach ($this->_fields as $field => $type) {
			if ($type == 'bool') {
				$this->$field = !empty($values[$field]) ? 0 : 1;
			} else {
				$this->$field = !blank($values[$field]) ? $values[$field] : '';
			}
		}
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
		if (empty($GLOBALS['config']['user_role']))
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
				foreach ($this->_ignoreFields as $field) {
					unset($values[$field]);
				}
			}
			#Update the data in the database.
			$this->_db->update($this->_table, $values, 'id=' . $id);
		} else {
			$this->id = $this->_db->insert($this->_table, $values);
		}
		return TRUE;
	}

	/**
	 * Load this object by ID
	 * 
	 * @param int $id
	 * @return boolean Success
	 */
	public function load($id)
	{
		$id = intval($id);

		if (empty($id))
			return FALSE;

		if (empty($this->_db))
			$this->connect();

		$values = $this->_db->run('SELECT * FROM `' . $this->_table . '` WHERE id = ' . $id);

		if (empty($values) || empty($values['id'])) {
			#show_error($id, 'Failed to load ' . $this->_class);
			return FALSE;
		}

		$this->fill($values);
		$this->id = $values['id'];
		return TRUE;
	}

	/**
	 * Delete this object (or specific id)
	 * 
	 * @param optional int $id
	 */
	public function delete($id = NULL)
	{
		if (empty($GLOBALS['config']['user_role']))
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
	public function getAll($where = '', $orderBy = NULL, $limit = NULL)
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
	public function getOne($where = '', $orderBy = NULL)
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

		return!empty($row) ? new $this->_class($row) : FALSE;
	}

	/**
	 * Get a basic array list for this object, for use in dropdowns.
	 * 
	 * @param string $where Basic SQL where structure.
	 * @param string $orderBy And order by.
	 * @return array Field structure.
	 */
	public function getSelectionList($where = '', $orderBy = NULL)
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
		$form->field('hidden', 'class');

		if (!empty($form->warning)) {
			$form->add('<div class="warning">' . $form->warning . '</div>');
		}

		#Current object selection.
		$list = array('' => '--new--') + $this->getSelectionList();
		$form->field('select', 'id', 'Current', array('values' => $list, 'class' => 'idSelect'));

		$form->fieldset('Values', array('class' => 'values ' . strtolower($this->_class)));
		foreach ($fields as $field => $type) {
			$this->getFormField($field, $type);
		}
		$form->field('submit', 'submit', NULL, array('value' => 'Save', 'class' => 'save'));
		$form->field('button', 'cancel', NULL, array('value' => 'Cancel', 'class' => 'cancel'));

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
	 * @param string $type
	 */
	public function getFormField($field, $type)
	{
		#Ignore these fields for editing.
		if (!empty($this->_ignoreFields) && in_array($field, $this->_ignoreFields))
			return;


		$form = $this->_form;

		# We only need the type, not anything else.
		$type = array_shift(explode('|', $type));
		$lastChars = substr($field, -2);

		$ttype = substr($type, -3);

		switch ($ttype) {
			#Text types.
			case 'ext':
				$form->field('text', $field, ucfirst($field));
				break;

			#Bool
			case 'ool':
				$form->field('check', $field, ucfirst($field));
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

					$list = self::getModelForClass($fieldClass);

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
			return TRUE;

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
		if (empty($GLOBALS['config']['user_role']))
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
	public static function loadModel($className)
	{
		$pclass = preg_replace('/[^a-z\_]/', '', strtolower($className));
		#Check if we have loaded this class before.
		if (!empty(self::$loadedClasses[$pclass]))
			return self::$loadedClasses[$pclass];

		if (empty($GLOBALS['config']['models']))
			show_exit('Models not configured');

		$models = $GLOBALS['config']['models'];

		#Get the lookup array.
		if (empty(self::$classLookup)) {
			$lookup = array();
			foreach ($models as $class => $file) {
				$lookup[strtolower($class)] = $class;
			}
			self::$classLookup = $lookup;
		} else {
			$lookup = self::$classLookup;
		}


		$realClass = !empty($lookup[$pclass]) ? $lookup[$pclass] : NULL;

		if (empty($realClass)) {
			show_error($className, 'Could not find Classname');
			return NULL;
		}

		#Load the model if needs be.
		if (!class_exists($realClass))
			Load::library($models[$realClass], 'model');

		self::$loadedClasses[$pclass] = $realClass;

		#Return the real classname.
		return $realClass;
	}

	/**
	 * Create an object of model Class, with validation so 'wrong' objects cannot be created.
	 * 
	 * @param string $class
	 * @param array|int $values
	 * @return Model A class, derived from model, based on $class.
	 */
	public static function getModelForClass($className, $values = NULL)
	{
		$realClass = self::loadModel($className);

		#Return the object.
		return new $realClass($values);
	}

}