<?php
/**

    The MIT License (MIT)

    Copyright (c) 2014, Kai Gohegan

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.

*/

namespace Platform;

/**
 * Templating class
 */
class platformTemplate {
	
	private $_templateSource = null;
	private $_templateCompiled = null;
	private $_templateVariables = array();
	
	function __construct($templateFile = false, $keysValues = false)
	{
		if($templateFile !== false)
		{
			$templateFilePath = pathinfo($templateFile);
			if($templateFilePath["dirname"] == ".")
			{
				$templateFilePath = "templates".DIRECTORY_SEPARATOR.$templateFile;
			} else {
				$templateFilePath = $templateFile;
			}
			if(file_exists($templateFilePath))
			{
				// Load variables
				if(is_array($keysValues))
				{
					$this->_templateVariables = $keysValues;
				} else {
					if(is_a($keysValues, "Platform\\platformRow"))
					{
						$this->_templateVariables = $keysValues->getArray();
					} else {
						throw new platformException("Template variables must be passed as an array of keys and values, or a platformRow object!");
					}
				}
				$this->_templateSource = file_get_contents($templateFilePath);
				// Compile the template
				$this->_compileTemplate();
			} else {
				throw new platformException("Unable to load template file - file not found: ".$templateFilePath);
			}
		} else {
			throw new platformException("Template source file must be passed as the first parameter to platformTemplate");
		}
	}
	
	/**
	 * If the class is called as a string,
	 * return the compiled template
	 */
	function __toString()
	{
		return $this->_templateCompiled;
	}
	
	/**
	 * Process the supplied template tag
	 */
	private function _processTag($tagMatches)
	{
		$tag = substr($tagMatches[0], 2, (strlen($tagMatches[0]) - 4));
		if(isset($this->_templateVariables[$tag]))
		{
			return $this->_templateVariables[$tag];
		} else {
			return "NULL";
		}
	}
	
	/**
	 * Process the supplied template logic
	 */
	private function _processLogic($tagMatches)
	{
		if(count($tagMatches == 4))
		{
			$operator = strtoupper($tagMatches[1]);
			$conditions = trim($tagMatches[2]);
			$body = $tagMatches[3];
			$return = true;
			switch($operator)
			{
				case "IF":
					$conditions = explode("||", $conditions);
					foreach($conditions as $condition)
					{
						if($this->_evaluateIFLogic($condition) === false)
						{
							$return = false;
							break;
						}
					}
					if($return === true)
					{
						return $body;
					} else {
						return "";
					}
					break;
				case "FOREACH":
					return $this->_evaluateFOREACHLogic($conditions, $body);
					break;
				default:
					return "[PLATFORM: Logic Error!]";
					break;
			}
		} else {
			return "[PLATFORM: Logic Error!]";
		}
	}
	
	/**
	 * Evaluate the supplied logic
	 */
	private function _evaluateIFLogic($logic)
	{
		$matches = array();
		if(preg_match("/({.*?})[ *](.{2})[ *](.*)/", $logic, $matches) === 1)
		{
			$var1 = null;
			$var2 = null;
			if(substr($matches[1], 0, 1) == "{")
			{
				$var1 = $this->_templateVariables[substr($matches[1], 1, (strlen($matches[1]) - 2))];
			} else {
				$var1 = substr($matches[1], 1, (strlen($matches[1]) - 2));
			}
			if(substr($matches[3], 0, 1) == "{")
			{
				$var2 = $this->_templateVariables[substr($matches[3], 1, (strlen($matches[3]) - 2))];
			} else {
				$var2 = substr($matches[3], 1, (strlen($matches[3]) - 2));
			}
			if(strtoupper($var1) == "TRUE" || strtoupper($var1) == "FALSE")
			{
				$var1 = (bool)$var1;
			}
			if(strtoupper($var2) == "TRUE" || strtoupper($var2) == "FALSE")
			{
				$var2 = (bool)$var2;
			}
			$operator = $matches[2];
			$return = false;
			// Parse the logic...
			switch($operator)
			{
				case "==":
					if($var1 == $var2)
					{
						$return = true;
					}
					break;
				case "!=":
					if($var1 != $var2)
					{
						$return = true;
					}
					break;
				case ">":
					if($var1 > $var2)
					{
						$return = true;
					}
					break;
				case "<":
					if($var1 < $var2)
					{
						$return = true;
					}
					break;
				case ">=":
					if($var1 >= $var2)
					{
						$return = true;
					}
					break;
				case "<=":
					if($var1 <= $var2)
					{
						$return = true;
					}
					break;
				default:
					$return = false;
					break;
			}
			return $return;
		} else {
			return false;
		}
	}

	/**
	 * Process the foreach logic
	 */
	private function _evaluateFOREACHLogic($condition, $body)
	{
		$matches = array();
		if(preg_match("/({.*?})[ *](as|AS)[ *]({.*?})/", $condition, $matches) === 1)
		{
			if(substr($matches[1], 0, 1) == "{")
			{
				$array = $this->_templateVariables[substr($matches[1], 1, (strlen($matches[1]) - 2))];
				$return = "";
				foreach($array as $tag)
				{
					$return .= str_replace($matches[3], $tag, $body);
				}
				return $return;
			} else {
				return "[PLATFORM: FOREACH error - invalid array passed]";
			}
		} else {
			return "[PLATFORM: FOREACH error]";
		}
	}
	
	/**
	 * Add a key / value pair to the
	 * template for processing
	 */
	public function addVariable($key = false, $value = false)
	{
		if($key !== false)
		{
			if(is_array($key))
			{
				// An array has been passed, so merge
				// with the current variables
				$this->_templateVariables = array_merge($this->_templateVariables, $key);
			} else {
				// Only a single key / value passed...
				$this->_templateVariables[$key] = $value;
			}
			// Re-compile the template
			$this->_compileTemplate();
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Remove a key / value pair
	 */
	public function removeVariable($key = false)
	{
		if($key !== false)
		{
			if(isset($this->_templateVariables[$key]))
			{
				unset($this->_templateVariables[$key]);
				// Re-compile the template
				$this->_compileTemplate();
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Get source
	 */
	public function templateSource()
	{
		return $this->_templateSource;
	}
	
	/**
	 * Get compiled template
	 */
	public function templateCompiled()
	{
		return $this->_templateCompiled;
	}
	
	/**
	 * Compile the template using the
	 * supplied variables
	 */
	private function _compileTemplate($subTemplate = false)
	{
		if($subTemplate === false)
		{
			// First, replace [{keys}] with the
			// equivalent value from the array
			$this->_templateCompiled = preg_replace_callback("/\\[\\{[a-zA-Z0-9]+\\}\\]/", array($this, "_processTag"), $this->_templateSource);
			// Now process template logic...
			$this->_templateCompiled = preg_replace_callback("^\\[\\(([a-zA-Z]+)\\)(.*)\\](.*)\\[\\(/\\1\\)\\]^", array($this, "_processLogic"), $this->_templateCompiled);
		} else {
			$output = null;
			$output = preg_replace_callback("/\\[\\{[a-zA-Z0-9]+\\}\\]/", array($this, "_processTag"), $subTemplate);
			return preg_replace_callback("^\\[\\(([a-zA-Z]+)\\)(.*)\\](.*)\\[\\(/\\1\\)\\]^", array($this, "_processLogic"), $output);
		}
	}
}
	