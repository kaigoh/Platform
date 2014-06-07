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
				$this->_db = new \PDO($dsn, $username, $password, $params);
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
		$drivers = \PDO::getAvailableDrivers();
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
			while($table = $tables->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT))
			{
				$this->_tables[] = current($table);
			}
			return $this->_tables;
		} else {
			return false;
		}
	}

}