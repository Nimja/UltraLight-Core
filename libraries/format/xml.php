<?php

/**
 * XML Formatter
 *
 * @author Zilvinas Saltys <zilvinas.saltys@gmail.com>
 * @url http://www.thedeveloperday.com/
 * @version 0.3.0
 * @package XML_Formatter
 */

/**
 * XML_Formatter class responsible for formatting an XML stream
 * to an indented human readable format.
 * 
 * @package XML_Formatter
 */
class XML_Formatter
{

	/**
	 * XML parser
	 *
	 * @var resource xml parser
	 */
	protected $_parser = null;

	/**
	 * Unformatted XML input string
	 *
	 * @var string input
	 */
	protected $_input = '';

	/**
	 * Formatted XML output string
	 *
	 * @var string output string
	 */
	protected $_output = '';

	/**
	 * Input stream offset index
	 *
	 * @var int stream offset index
	 */
	protected $_offset = 0;

	/**
	 * XML depth index
	 *
	 * @var int XML depth index
	 */
	protected $_depth = 0;

	/**
	 * Closed XML element type flag
	 *
	 * @var boolean closed XML element type flag
	 */
	protected $_empty = false;

	/**
	 * Input stream buffer
	 *
	 * @var string input stream buffer
	 */
	protected $_buffer = "";

	/**
	 * Formatter options
	 *
	 * =====> (int) bufferSize:
	 * - Buffer size in kilobytes
	 *
	 * =====> (string) paddingString:
	 * - Padding string used for indentation
	 *
	 * =====> (int) paddingMultiplier:
	 * - Padding multiplier used to multiply padding string
	 *
	 * =====> (boolean) formatCData:
	 * - Flag whether to format character data. May be useful in some cases.
	 *
	 * =====> (boolean) multipleLineCData:
	 * - Flag whether character data consists of multiple lines.
	 *
	 * =====> (int|false) wordwrapCData:
	 * - Character data wordwrap length
	 * - If false does not wordwrap character data
	 *
	 * =====> (string) inputEOL:
	 * - Input stream character data end of line string
	 *
	 * =====> (string) outputEOL:
	 * - Output stream character data end of line string
	 *
	 * @var array formatter options
	 */
	protected $_options = array(
		"paddingString" => "\t",
		"paddingMultiplier" => 1,
		"formatCData" => TRUE,
		"multipleLineCData" => TRUE,
		"wordwrapCData" => 75,
		"inputEOL" => "\n",
		"outputEOL" => "\n"
	);

	/**
	 * Constructor
	 *
	 * @param resource $input Input stream
	 * @param resource $output Output stream
	 * @return void
	 */
	public function __construct($input, Array $options = array())
	{
		$this->_input = $input;
		$this->_output = '';
		$this->_options = array_merge($this->_options, $options);

		$this->_parser = xml_parser_create();

		xml_set_object($this->_parser, $this);

		xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->_parser, XML_OPTION_SKIP_WHITE, 0);

		xml_set_element_handler($this->_parser, "_cbElementStart", "_cbElementEnd");
		xml_set_character_data_handler($this->_parser, "_cbCharacterData");
	}

	/**
	 * Get padding string relative to XML depth index
	 *
	 * @param void
	 * @return string padding string
	 */
	protected function _getPaddingStr()
	{
		return str_repeat($this->_options["paddingString"], $this->_depth * $this->_options["paddingMultiplier"]);
	}

	/**
	 * Element start callback
	 *
	 * @param resource $parser xml parser
	 * @param string $name element name
	 * @param array $attributes element attributes
	 * @return void
	 */
	protected function _cbElementStart($parser, $name, Array $attributes)
	{
		$idx = xml_get_current_byte_index($this->_parser);

		$this->_empty = $this->_buffer[$idx - $this->_offset] == '/';

		$attrs = "";
		foreach ($attributes as $key => $val) {
			$attrs .= " " . $key . "=\"" . $val . "\"";
		}

		$this->_output .= $this->_getPaddingStr() . "<" . $name . $attrs . ($this->_empty ? ' />' : '>') . "\n";

		if (!$this->_empty)
			++$this->_depth;
	}

	/**
	 * Element end callback
	 *
	 * @param resource $parser xml parser
	 * @param string $name element name
	 * @return void
	 */
	protected function _cbElementEnd($parser, $name)
	{
		if (!$this->_empty) {
			--$this->_depth;

			$this->_output .= $this->_getPaddingStr() . "</" . $name . ">" . "\n";
		} else {
			$this->_empty = false;
		}
	}

	/**
	 * Character data callback
	 *
	 * @param resource $parser xml parser
	 * @param string $data character data
	 * @return void
	 */
	protected function _cbCharacterData($parser, $data)
	{
		if (!$this->_options["formatCData"]) {
			return;
		}

		$data = trim($data);

		if (strlen($data)) {
			$pad = $this->_getPaddingStr();

			if ($this->_options["multipleLineCData"]) {

				// remove all tabs
				$data = str_replace("\t", "", $data);

				// append each line with a padding string
				$data = implode($this->_options["inputEOL"] . $pad, explode($this->_options["inputEOL"], $data));
			}

			if ($this->_options["wordwrapCData"]) {
				$data = wordwrap($data, $this->_options["wordwrapCData"], $this->_options["outputEOL"] . $pad, false);
			}

			$this->_output .= $pad . $data . "\n";
		}
	}

	/**
	 * Main format method
	 *
	 * @param void
	 * @throws Exception
	 * @return void
	 */
	public function format()
	{
		if (!xml_parse($this->_parser, $this->_input, TRUE)) {
			throw new Exception(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->_parser)), xml_get_current_line_number($this->_parser)));
			return;
		}
		xml_parser_free($this->_parser);
	}

	/**
	 * Get the output.
	 * @return string output.
	 */
	public function getOutput() {
		return $this->_output;
	}
}