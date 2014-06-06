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

/**
 * Base Application class
 */
class platformApp {

	// Misc.
	private $_appRunning = false;

	// Request data
	private $_requestRaw = null;
	private $_request = null;

	// URL segments
	private $_urlSegments = array();

	// GET data
	private $_getParams = array();

	// Routes
	private $_routeRaw = null;
	private $_customRoutes = array();
	private $_customRoute = false;

	private $_defaultRoute = array(
		"controller" => "defaultRoutes",
		"method" => "index",
		"extension" => null,
	);

	private $_errorRoute = array(
		"controller" => "defaultRoutes",
		"method" => "error404",
		"extension" => null,
	);

	private $_route = array(
		"controller" => null,
		"method" => "index",
		"extension" => null,
	);

	function __construct($defaultRoute = false, $errorRoute = false)
	{
	    // Override the default route if supplied
	    if($defaultRoute !== false && is_array($defaultRoute))
	    {
	        $this->_defaultRoute = $defaultRoute;
	    }
	    // Override the error route if supplied
	    if($errorRoute !== false && is_array($errorRoute))
	    {
	        $this->_errorRoute = $errorRoute;
	    }
	}

	// Away we go!
	public function runApplication()
	{
		// Set application status
		$this->_appRunning = true;
		// Process the route URL
		$this->_requestRaw = preg_replace('/&/', '?', $_SERVER['QUERY_STRING'], 1);
		$this->_request = explode("?", $this->_requestRaw);
		$this->_routeRaw = pathinfo($this->_request[0]);

		// Process the URL segments
		$this->_urlSegments = explode("/", strtolower($this->_routeRaw["dirname"]));

		// Process custom routes
		foreach($this->_customRoutes as $customRoute)
		{
			if($this->_customRoute === false)
			{
				if($customRoute["url"] == $this->_request[0] && (strtoupper($_SERVER['REQUEST_METHOD']) == $customRoute["requestmethod"] || $customRoute["requestmethod"] == "*"))
				{
					// Got a perfect match, skip checking the rest...
					$this->_customRoute = array(
						"controller" => $customRoute["controller"],
						"method" => $customRoute["method"],
						"extension" => strtolower($this->_routeRaw["extension"]),
					);
				} else {
					if(fnmatch($customRoute["url"], $this->_request[0]) && (strtoupper($_SERVER['REQUEST_METHOD']) == $customRoute["requestmethod"] || $customRoute["requestmethod"] == "*"))
					{
						$this->_customRoute = array(
							"controller" => $customRoute["controller"],
							"method" => $customRoute["method"],
							"extension" => strtolower($this->_routeRaw["extension"]),
						);
					}
				}
			}
		}

		if(is_array($this->_customRoute))
		{
			$this->_route = $this->_customRoute;
		} else {
			if(strlen($this->_routeRaw["dirname"]) > 0)
			{
				if(strlen($this->_routeRaw["basename"]) > 0)
				{
					if($this->_routeRaw["dirname"] == ".")
					{
						$this->_route["controller"] = strtolower($this->_routeRaw["filename"]);
					} else {
						$this->_route["controller"] = $this->_urlSegments[0];
						$this->_route["method"] = strtolower($this->_routeRaw["filename"]);
					}
					if(isset($this->_routeRaw["extension"]))
					{
						$this->_route["extension"] = strtolower($this->_routeRaw["extension"]);
					}
				} else {
					$this->_route["controller"] = $this->_urlSegments[0];
					$this->_route["method"] = "index";
				}
			} else {
				// No URL supplied, so set the default route
				$this->_route = $this->_defaultRoute;
			}
		}

		// Process GET data
		$this->_getParams = array();
		if(count($this->_request) > 0)
		{
			// Split the GET params up
			$getRaw = explode("&", $this->_request[1]);
			foreach($getRaw as $paramRaw)
			{
				$param = explode("=", $paramRaw);
				if(count($param) > 0)
				{
					$this->_getParams[$param[0]] = $param[1];
				} else {
					$this->_getParams[$param[0]] = null;
				}
			}
		}
		// Fix the $_GET array
		$_GET = $this->_getParams;

		if($this->_routeRequest($this->_route) === false)
		{
			// Set 404 header
			header("HTTP/1.0 404 Not Found");
			if($this->_routeRequest($this->_errorRoute) === false)
			{
				echo "404 - Cannot route request. Also, the 404 error route was not found :(";
			}
		}
	}

	// Add custom routes
	public function addRoute($url = false, $controller = false, $method = false, $requestMethod = "*")
	{
		if($this->_appRunning == false)
		{
			if($url !== false && $controller !== false && $method !== false)
			{
				$this->_customRoutes[] = array("url" => $url, "controller" => $controller, "method" => $method, "requestmethod" => strtoupper($requestMethod));
				sort($this->_customRoutes);
				return true;
			} else {
				return false;
			}
		} else {
			throw new platformException("Cannot add a route to a running Platform application!");
		}
	}

	// Load coresponding class and execute the matching function
	private function _routeRequest($route)
	{
		if(file_exists("controllers/".$route["controller"].".php"))
		{
			require("controllers/".$route["controller"].".php");

			// Some page variables
			$controllerName = $route["controller"];
			$pageName = $route["method"];
			$requestMethodPageName = $_SERVER['REQUEST_METHOD'].$pageName;
			$pageExtension = $route["extension"];
			$pageNameExtension = $pageName.$pageExtension;
			$requestMethodPageNameExtension = $_SERVER['REQUEST_METHOD'].$pageName.$pageExtension;
			$extensionHandler = "handler".$pageExtension;

			// Instantiate the correct model
			$controller = new $controllerName($this->_requestRaw, array_slice($this->_urlSegments, 1), $_SERVER['REQUEST_METHOD'], $pageName, $pageExtension);

			/**
			 * Router matches in the following order
			 * 1) Request Type (GET, POST etc.) + Page Name (Method / Function) + File Extension
			 * 2) Page Name (Method / Function) + File Extension
			 * 3) Request Type (GET, POST etc.) + Page Name (Method / Function)
			 * 4) Page Name (Method / Function)
			 * 5) Error (404) Handler
			 */
			if(method_exists($controller, $requestMethodPageNameExtension))
			{
				// Call the extension handler for the requested
				// file type (if method exists). Can be used
				// to set any headers, for example JSON or JavaScript
				if(method_exists($controller, $extensionHandler))
				{
					$controller->$extensionHandler();
				}
				// Now call the desired page method
				$controller->$requestMethodPageNameExtension();
			} else {
				// Try to match based on the page name
				// and the file extension
				if(method_exists($controller, $pageNameExtension))
				{
					// Call the extension handler for the requested
					// file type (if method exists). Can be used
					// to set any headers, for example JSON or JavaScript
					if(method_exists($controller, $extensionHandler))
					{
						$controller->$extensionHandler();
					}
					// Now call the desired page method
					$controller->$pageNameExtension();
				} else {
    				// Unable to match the method on extension,
    				// so try to match on request method and
    				// page name
    				if(method_exists($controller, $requestMethodPageName))
    				{
    					$controller->$requestMethodPageName();
					} else {
						// Try to match based on just the page name
						if(method_exists($controller, $pageName))
						{
							$controller->$pageName();
						} else {
							// Unable to match, so return a 404
							// error to the browser
							return false;
						}
					}
				}
			}
		} else {
			return false;
		}
	}

}

/**
 * Exception class
 */
class platformException extends Exception
{
	// ToDo
}

/**
 * Base Controller class
*/
class platformController {

	protected $rawRequest = null;
	protected $urlSegments = array();
	protected $pageRequestMethod = null;
	protected $pageName = null;
	protected $pageExtension = null;

	// Autoloader
	protected $_libraryLoader = null;

	function __construct($requestRaw, $urlSegments, $pageRequestMethod, $pageName, $pageExtension)
	{
	    $this->rawRequest = $requestRaw;
	    $this->urlSegments = $urlSegments;
		$this->pageRequestMethod = $pageRequestMethod;
		$this->pageName = $pageName;
		$this->pageExtension = $pageExtension;

		// Composer auto loader
		if(file_exists("vendor/autoload.php"))
		{
		    $this->_libraryLoader = require("vendor/autoload.php");
		} else {
		    $this->_libraryLoader = false;
		}

	}

	// File-type handler - JavaScript
	public function handlerJS()
	{
		header("Content-Type: application/javascript");
	}

	// File-type handler - JSON
	public function handlerJSON()
	{
		header("Content-Type: application/json");
	}

	// File-type handler - PDF
	public function handlerPDF()
	{
		header("Content-Type: application/pdf");
	}

}

/**
 * Database Class
 */
class platformDatabase {

	private $_db = null;
	private $_tables = array();

	/**
	 * Initialise the class with DSN string and optional
	 * username, password and PDO parameters
	 */
	function __construct($dsn = false, $username = null, $password = null, $params = array())
	{
		if(self::getPDODrivers() !== false)
		{
			if($dsn !== false)
			{
				$this->_db = new PDO($dsn, $username, $password, $params);
				// Try and get a list of tables from the database
				$this->getTables();
			} else {
				throw new platformException("DSN string cannot be empty!");
			}
		} else {
			throw new platformException("Host system does not have any PDO drivers available!");
		}
	}

	/**
	 * "Magic" get method - returns a platformTable object
	 * if the requested property is a valid table name
	 */
	public function __get($tableName)
	{
		// Update the list of tables to ensure
		// we have the latest ones
		$this->getTables();
		if(in_array($tableName, $this->_tables))
		{
			return new platformTable(&$this, $tableName);
		} else {
			return false;
		}
	}

	/**
	 * "Magic" isset method - returns true or false
	 * if the table exists
	 */
	public function __isset($tableName)
	{
		// Update the list of tables to ensure
		// we have the latest ones
		$this->getTables();
		if(in_array($tableName, $this->_tables))
		{
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns the PDO drivers installed on the
	 * host system
	 */
	public static function getPDODrivers()
	{
		$drivers = PDO::getAvailableDrivers();
		if(count($drivers) > 0)
		{
			return $drivers;
		} else {
			return false;
		}
	}

	/**
	 * Get a PDO attribute
	 */
	public function getPDOAttribute($attr = false)
	{
		if($this->_db !== null && $attr !== false)
		{
			return $this->_db->getAttribute($attr);
		} else {
			return false;
		}
	}

	/**
	 * Set a PDO attribute
	 */
	public function setPDOAttribute($attr = false, $value = false)
	{
		if($this->_db !== null && $attr !== false)
		{
			return $this->_db->setAttribute($attr, $value);
		} else {
			return false;
		}
	}

	/**
	 * Return SQL error information
	 */
	public function lastDatabaseError()
	{
		if($this->_db !== null)
		{
			return $this->_db->errorInfo();
		} else {
			return false;
		}
	}

	/**
	 * Start a PDO transaction
	 */
	public function beginTransaction()
	{
		if($this->_db !== null)
		{
			return $this->_db->beginTransaction();
		} else {
			return false;
		}
	}

	/**
	 * Commit a PDO transaction
	 */
	public function commitTransaction()
	{
		if($this->_db !== null && $this->_db->inTransaction() === true)
		{
			return $this->_db->commit();
		} else {
			return false;
		}
	}

	/**
	 * Rollback (undo) a PDO transaction
	 */
	public function rollbackTransaction()
	{
		if($this->_db !== null)
		{
			return $this->_db->rollback();
		} else {
			return false;
		}
	}

	/**
	 * Check transaction status
	 */
	public function isTransaction()
	{
		if($this->_db !== null)
		{
			return $this->_db->inTransaction();
		} else {
			return false;
		}
	}

	/**
	 * Return the ID of the last database insert
	 */
	public function lastInsertID()
	{
		if($this->_db !== null)
		{
			return $this->_db->lastInsertId();
		} else {
			return false;
		}
	}

	/**
	 * Prepare and execute SQL
	 */
	public function prepareAndExecuteSQL($sqlStatement = false, $sqlData = false, $pdoOptions = null)
	{
		if($this->_db !== null && $sqlStatement !== false && $sqlData !== false)
		{
			$sql = null;
			if($pdoOptions !== null)
			{
				$sql = $this->_db->prepare($sqlStatement, $pdoOptions);
			} else {
				$sql = $this->_db->prepare($sqlStatement);
			}
			$sql->execute($sqlData);
			return $sql;
		} else {
			return false;
		}
	}

	/**
	 * UPDATE a table
	 */
	public function updateTable($table = false, $whereFields = false, $setFields = false)
	{
		if($this->_db !== null && $table !== false && $whereFields !== false && $setFields !== false)
		{
			$setParams = array();
			foreach($setFields as $column => $data)
			{
				$setParams[] = $column." = :".$column;
			}
			$whereParams = array();
			foreach($whereFields as $column => $data)
			{
				$whereParams[] = $column." = :".$column;
			}
			$sql = $this->_db->prepare("UPDATE ".$table." SET ".implode(", ", $setParams)." WHERE ".implode(" AND ", $whereParams));
			// Bind the parameters to the query
			$queryFields = array_merge($setFields, $whereFields);
			$queryFieldsClean = array();
			foreach($queryFields as $column => $data)
			{
				$queryFieldsClean[":".$column] = $data;
			}
			$sql->execute($queryFieldsClean);
			return $sql->rowCount();
		} else {
			return false;
		}
	}

	/**
	 * DELETE from a table
	 */
	public function deleteFromTable($table = false, $whereFields = false)
	{
		if($this->_db !== null && $table !== false && $whereFields !== false)
		{
			$whereParams = array();
			foreach($whereFields as $column => $data)
			{
				$whereParams[] = $column." = :".$column;
			}
			$sql = $this->_db->prepare("DELETE FROM ".$table." WHERE ".implode(" AND ", $whereParams));
			// Bind the parameters to the query
			$queryFieldsClean = array();
			foreach($whereParams as $column => $data)
			{
				$queryFieldsClean[":".$column] = $data;
			}
			$sql->execute($queryFieldsClean);
			return $sql->rowCount();
		} else {
			return false;
		}
	}

	/**
	 * Process SQL column names
	 * Returns a clean string of column names
	 */
	public function processColumns($columns = "*")
	{
		$columnsClean = array();
		if(is_array($columns))
		{
			foreach($columns as $column)
			{
				$column = str_replace(" as ", " AS ", $column);
				$columnsClean[] = $column;
			}
			$columnsClean = implode(", ", $columnsClean);
		} else {
			if(is_string($columns) && strlen($columns) > 0)
			{
				$columnsClean = $columns;
			} else {
				$columnsClean = false;
			}
		}
		return $columnsClean;
	}

	/**
	 * Escape a string for an SQL query
	 */
	public function escapeString($string = false)
	{
		if($this->_db !== null && $string !== false && strlen($string) > 0)
		{
			return $this->_db->quote($string);
		} else {
			return false;
		}
	}

	/**
	 * Execute a raw SQL query - this is NOT escaped
	 * automatically!
	 */
	public function executeSQLQuery($query = false)
	{
		if($this->_db !== null && $query !== false && strlen($query) > 0)
		{
			return $this->_db->query($query);
		} else {
			return false;
		}
	}

	/**
	 * Execute a raw SQL statement - this is NOT escaped
	 * automatically!
	 */
	public function executeSQLStatement($statement = false)
	{
		if($this->_db !== null && $statement !== false && strlen($statement) > 0)
		{
			return $this->_db->exec($statement);
		} else {
			return false;
		}
	}

	/**
	 * List the tables in this database
	 */
	public function getTables()
	{
		$tables = $this->executeSQLQuery("SHOW TABLES");
		if($tables !== false)
		{
			while($table = $tables->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT))
			{
				$this->_tables[] = current($table);
			}
			return $this->_tables;
		} else {
			return false;
		}
	}

}

/**
 * Database table class
 */
class platformTable {

	private $_database = false;
	private $_table = false;
	private $_columns = array();
	private $_primaryKey = false;

	private $_selectParams = "*";
	private $_distinct = "";
	private $_whereParams = array();
    private $_valueBindings = array();
	private $_queryStartRow = false;
	private $_queryLimit = false;
	private $_rowCount = 0;
	private $_lastQuery = null;

	function __construct($platformDatabase = false, $tableName = false)
	{
		if($pdoObject !== false && $tableName !== false)
		{
			$this->_database = $platformDatabase;
			$this->_table = $tableName;
			$this->getColumns();
		} else {
			throw new platformException("Unable to instatiate platformTable class without valid PDO object and table name!");
		}
	}

	/**
	 * Gets the database object
	 */
	public function getDatabase()
	{
		return $this->_database;
	}

	/**
	 * Returns the name of this table
	 */
	public function getTableName()
	{
		return $this->_table;
	}

	/**
	 * Returns the table's primary key
	 */
	public function getPrimaryKey()
	{
		return $this->_primaryKey;
	}

	/**
	 * Manually set the tables primary key
	 */
	public function setPrimaryKey($columnName = false)
	{
		$columns = $this->getColumns();
		if($columnName !== false && in_array($columnName, $columns))
		{
			$this->_primaryKey = $columnName;
		} else {
			return false;
		}
	}

	/**
	 * Return an array containing the
	 * columns from this table. Also sets
	 * the primary key column if found.
	 */
	public function getColumns()
	{
		$columns = $this->_database->executeSQLQuery("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ".$this->_database->escapeString($this->_table));
		if($columns !== false)
		{
			while($column = $columns->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT))
			{
				// Check for primary key column
				if($column["COLUMN_KEY"] == "PRI")
				{
					$this->_primaryKey = $column["COLUMN_NAME"];
				}
				$this->_columns[] = $column["COLUMN_NAME"];
			}
			return $this->_columns;
		} else {
			return false;
		}
	}

	/**
	 * Fetch all rows from the table (SELECT * FROM x).
	 * If either an array of columns or an SQL string is
	 * passed, it will include these in the query
	 * eg: $table->getAllRows(array("col1", "col2", "col4 as col3"));
	 */
	public function getAllRows($columns = "*")
	{
		$columnsClean =  $this->_database->processColumns($columns);
		if($columnsClean !== false)
		{
			$allRows = $this->_database->executeSQLQuery("SELECT ".$columnsClean." FROM `".$this->_table."`");
			if($allRows !== false)
			{
				return $allRows->fetchAll(PDO::FETCH_CLASS, "platformRow", array($this->_database, $this));
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Save row - This method checks that
	 * the columns exist in the table before
	 * attempting to update the row
	 */
	public function saveRow(&$row)
	{
		if($this->_database !== false && $this->_table !== false && is_a($row, "platformRow"))
		{
			// Fetch the data from the row
			$rowArray = $row->getArray();
			$rowOriginal = $row->getOriginal();
			// Find the fields that are different
			$updatedFields = array();
			foreach($rowArray as $column => $data)
			{
				if(array_key_exists($column, $rowOriginal) && $data !== $rowOriginal[$column])
				{
					$updatedFields[$column] = $data;
				}
			}
			// Only bother with the query if
			// any fields have actually changed
			if(count($updatedFields) > 0)
			{
				// If the table has a primary key, use it in the query.
				// if not, fall back to a different method
				if($this->getPrimaryKey())
				{
					if($this->_database->updateTable($this->getTableName(), array($this->getPrimaryKey() => $rowArray[$this->getPrimaryKey()]), $updatedFields) > 0)
					{
						return true;
					} else {
						return false;
					}
				} else {
					if($this->_database->updateTable($this->getTableName(), $rowOriginal, $updatedFields) > 0)
					{
						return true;
					} else {
						return false;
					}
				}
			} else {
				return false;
			}
		} else {
			throw new platformException("Cannot update row!");
		}
	}

	/**
	 * Delete row - This method deletes the
	 * row from the table
	 */
	public function deleteRow(&$row)
	{
		if($this->_database !== false && $this->_table !== false && is_a($row, "platformRow"))
		{
			// Fetch the data from the row
			$rowArray = $row->getArray();
			$rowOriginal = $row->getOriginal();
			// If the table has a primary key, use it in the query.
			// if not, fall back to a different method
			if($this->getPrimaryKey() !== false)
			{
				if($this->_database->deleteFromTable($this->getTableName(), array($this->getPrimaryKey() => $rowArray[$this->getPrimaryKey()])) > 0)
				{
					unset($row);
					return true;
				} else {
					return false;
				}
			} else {
				if($this->_database->deleteFromTable($this->getTableName(), $rowOriginal) > 0)
				{
					unset($row);
					return true;
				} else {
					return false;
				}
			}
		} else {
			throw new platformException("Cannot delete row!");
		}
	}

	/**
	 * Returns the number of affected
	 * or returned rows from the last query
	 */
	public function totalRows()
	{
		if(!is_null($this->_lastQuery))
		{
			return $this->_lastQuery->rowCount();
		} else {
			return false;
		}
	}

	/**
	 * Performs a raw SQL query and returns
	 * an array of platformRows.
	 * **NOT ESCAPED! Be careful**
	 */
	public function query($sql = false)
	{
		if($this->_database !== false && $this->_table !== false && $sql !== false)
		{
			$rows = $this->_database->executeSQLQuery($sql);
			$this->_lastQuery = $rows;
			if($rows !== false)
			{
				return $rows->fetchAll(PDO::FETCH_CLASS, "platformRow", array($this->_database, $this));
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Query building functions - get
	 * Builds and performs the actual query
	 */
	public function get()
	{
		if($this->_database !== false && $this->_table !== false)
		{
			$selectParams = $this->_selectParams;
			$whereParams = implode(" AND ", $this->_whereParams);
			$limit = "";
			if($this->_queryLimit !== false)
			{
				$limit = " LIMIT ".$this->_queryLimit;
			}
			if($this->_queryStartRow !== false)
			{
				$limit .= ", ".$this->_queryStartRow;
			}
			$sql = $this->_database->prepareAndExecuteSQL("SELECT ".$this->_distinct.$selectParams." FROM `".$this->_table."` WHERE ".$whereParams.$limit, $this->_valueBindings);
			$this->_lastQuery = $sql;
			// Clear the query
			$this->reset();
			return $sql->fetchAll(PDO::FETCH_CLASS, "platformRow", array($this->_database, $this));
		} else {
			return false;
		}
	}

	/**
	 * Query building functions - getOne
	 * Builds and performs the actual query,
	 * but returns only the first matching row
	 */
	public function getOne()
	{
		if($this->_database !== false && $this->_table !== false)
		{
			$selectParams = $this->_selectParams;
			$whereParams = implode(" AND ", $this->_whereParams);
			$sql = $this->_database->prepareAndExecuteSQL("SELECT ".$this->_distinct.$selectParams." FROM `".$this->_table."` WHERE ".$whereParams, $this->_valueBindings);
			$this->_lastQuery = $sql;
			// Clear the query
			$this->reset();
			return $sql->fetchObject("platformRow", array($this->_database, $this));
		} else {
			return false;
		}
	}

	/**
	 * Query building functions - reset
	 * Resets the where and column clauses.
	 */
	public function reset()
	{
		if($this->_database !== false && $this->_table !== false)
		{
			$this->_selectParams = "*";
			$this->_whereParams = array();
			$this->_valueBindings = array();
			$this->_queryLimit = 1;
			$this->_distinct = "";
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Query building functions - distinct
	 */
	public function distinct()
	{
		$this->_distinct = " DISTINCT ";
		return true;
	}

	/**
	 * Query building functions - select
	 * Allows the building of the select
	 * statement. If either an array of columns
	 * or an SQL string is passed, it will
	 * include these in the query.
	 * eg: $table->select(array("col1", "col2", "col4 as col3"));
	 */
	public function select($columns = false)
	{
		if($this->_database !== false && $this->_table !== false && $columns !== false)
		{
			$this->_selectParams = $this->_database->processColumns($columns);
		} else {
			return false;
		}
	}

	/**
	 * Query building functions - where
	 * Allows the building of the where
	 * clause of an SQL query. Designed
	 * to be called multiple times.
	 */
    public function where($column = false, $operator = false, $value = false)
    {
    	if($this->_database !== false && $this->_table !== false && $column !== false && $operator !== false && $value !== false)
		{
	    	$binding = ":bind".strtoupper($column);
	        $this->_whereParams[] = $column." ".$operator." ".$binding;
	        $this->_valueBindings[$binding] = $value;
			return true;
		} else {
			return false;
		}
    }

	/**
	 * Query builder functions - limit
	 * Sets the limit parameter of the
	 * query. Also allows the setting
	 * of the starting row.
	 */
	public function limit($limit = false, $startRow = false)
	{
		if($this->_database !== false && $this->_table !== false && $limit !== false)
		{
			$this->_queryLimit = $limit;
			$this->_queryStartRow = $startRow;
			return true;
		} else {
			return false;
		}
	}

}

/**
 * Platform database row class
 */
class platformRow {

	private $_database = false;
	private $_table = false;
	private $_tableColumns = false;
	private $_rowData = array();
	private $_rowDataOriginal = array();

	function __construct($platformDatabase = false, $platformTable = false)
	{
		if($platformDatabase !== false && $platformTable !== false)
		{
			$this->_database = $platformDatabase;
			$this->_table = $platformTable;
			$this->_tableColumns = $this->_table->getColumns();
		}
	}

	/*
	 * "Magic" function - Set property
	 */
	public function __set($columnName, $columnValue)
	{
		if($this->_table == false)
		{
			$this->_rowDataOriginal[$columnName] = $columnValue;
			$this->_rowData[$columnName] = $columnValue;
		} else {
			if(array_key_exists($columnName, $this->_tableColumns))
			{
				$this->_rowData[$columnName] = $columnValue;
			} else {
				throw new platformException("Column you are trying to set does not exist in ".$this->_table->getTableName()." table.");
			}
		}
	}

	/*
	 * "Magic" function - Get property
	 */
	public function __get($columnName)
	{
		if(array_key_exists($columnName, $this->_rowData))
		{
			return $this->_rowData[$columnName];
		}
	}

	/*
	 * "Magic" function - Is property set?
	 */
	public function __isset($columnName)
	{
		if(array_key_exists($columnName, $this->_rowData))
		{
			return true;
		} else {
			return false;
		}
	}

	/**
	 * "Magic" function - If the row is called as
	 * a string, return the row as a JSON object
	 */
	public function __toString()
	{
		return json_encode($this->_rowData);
	}

	/**
	 * Return the row data as an array
	 */
	public function getArray()
	{
		return $this->_rowData;
	}

	/**
	 * Return the oringinal row data
	 * as loaded from the database
	 */
	public function getOriginal()
	{
		return $this->_rowDataOriginal;
	}

}
