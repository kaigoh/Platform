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
	private $_joins = array();
	private $_lastQuery = null;

	function __construct(&$platformDatabase = false, $tableName = false)
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
	 * `Magic` method - Attempts to return the
	 * table row that has the primary key supplied,
	 * ie. $table->pk1 would try and return the row whose
	 * primary key column has a value of 1. **NOTE** Because
	 * PHP variables cannot start with an integer, you must
	 * prefix the primaryu key value with "pk" (ie. pRIMARY kEY)
	 */
	function __get($primaryKey = false)
	{
		if($this->_primaryKey !== false && substr($primaryKey, 0, 2) == "pk")
		{
			$primaryKey = substr($primaryKey, 2);
			$sql = $this->_database->prepareAndExecuteSQL("SELECT * FROM `".$this->_table."` WHERE ".$this->_primaryKey." = :bind".$this->_primaryKey, array(":bind".$this->_primaryKey => $primaryKey));
			$this->_lastQuery = $sql;
			return $sql->fetchObject("Platform\\platformRow", array($this->_database, $this));
		} else {
			return false;
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
			$this->_columns = array();
			while($column = $columns->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT))
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
				return $allRows->fetchAll(\PDO::FETCH_CLASS, "Platform\\platformRow", array($this->_database, $this));
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * New row - Returns a platformRow object
	 * that can be inserted using the saveRow()
	 * function.
	 */
	public function newRow()
	{
		$newRow = new platformRow($this->_database, $this, true);
		foreach($this->_columns as $column)
		{
			$newRow->$column = null;
		}
		return $newRow;
	}

	/**
	 * Save row - This method checks that
	 * the columns exist in the table before
	 * attempting to update the row
	 */
	public function saveRow(&$row)
	{
		if($this->_database !== false && $this->_table !== false && is_a($row, "Platform\\platformRow"))
		{
			// Fetch the data from the row
			$rowArray = $row->getArray();
			$rowOriginal = $row->getOriginal();
			// Is this a new row, or one that needs updating?
			if($row->isNewRow() === true)
			{
				if($this->_database->insertRow($this->getTableName(), $rowArray) > 0)
				{
					// Update the row accordingly
					$row->isNewRow(array("column" => $this->getPrimaryKey(), "value" => $this->_database->lastInsertID()));
					return true;
				} else {
					return false;
				}
			} else {
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
		if($this->_database !== false && $this->_table !== false && is_a($row, "Platform\\platformRow"))
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
				return $rows->fetchAll(\PDO::FETCH_CLASS, "Platform\\platformRow", array($this->_database, $this));
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
			$joins = " ";
			if(count($this->_joins) > 0)
			{
				$joins .= implode(" ", $this->_joins)." ";
			}
			$sql = $this->_database->prepareAndExecuteSQL("SELECT ".$this->_distinct.$selectParams." FROM `".$this->_table."`".$joins."WHERE ".$whereParams.$limit, $this->_valueBindings);
			$this->_lastQuery = $sql;
			// Clear the query
			$this->reset();
			return $sql->fetchAll(\PDO::FETCH_CLASS, "Platform\\platformRow", array($this->_database, $this));
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
			$joins = " ";
			if(count($this->_joins) > 0)
			{
				$joins .= implode(" ", $this->_joins)." ";
			}
			$sql = $this->_database->prepareAndExecuteSQL("SELECT ".$this->_distinct.$selectParams." FROM `".$this->_table."`".$joins."WHERE ".$whereParams, $this->_valueBindings);
			$this->_lastQuery = $sql;
			// Clear the query
			$this->reset();
			return $sql->fetchObject("Platform\\platformRow", array($this->_database, $this));
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
			$this->_joins = array();
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
	 * clause of an SQL query. Can
	 * be called multiple times.
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
	 * Query building functions - join
	 * Allows JOINs to be built. Can be
	 * called multiple times.
	 */
	public function join(&$joinTable = false, $joinColumns = false, $joinType = false, $returnString = false)
	{
		if(is_a($joinTable, "Platform\\platformTable") && is_array($joinColumns))
		{
			$joinColumnsClean = "";
			foreach($joinColumns as $aColumn => $bColumn)
			{
				// Check if the column names
				// have been prefixed with
				// their table names
				if(strpos($aColumn, ".") === false)
				{
					$aColumn = $this->_table.".".$aColumn;
				}
				if(strpos($bColumn, ".") === false)
				{
					$bColumn = $this->_table.".".$bColumn;
				}
				$joinColumnsClean[] = $aColumn." = ".$bColumn;
			}
			// Glue the columns together with an 'AND'
			$joinColumnsClean = implode(" AND ", $joinColumnsClean);
			if($joinType !== false)
			{
				// Add a space to the
				// join type...
				$joinType .= " ";
			}
			$joinString = $joinType."JOIN ON ".$joinColumnsClean;
			if($returnString === true)
			{
				return $joinString;
			} else {
				$this->_joins[] = $joinString;
				return true;
			}
		} else {
			throw new platformException("Error: platformTable::join() expects the first parameter to be a platformTable object, the second parameter to be an array of coulmn names to join on (eg. acolumn => bcolumn - Note: Table names are automatically prefixed to the column names!) and an optional third parameter specifying the type of SQL JOIN.");
		}
	}

	/**
	 * Query building functions - join
	 * Allows importing JOIN statements
	 * from other platformTable objects
	 * to allow joining on more than two
	 * tables.
	 */
	public function importJOIN($sql = false)
	{
		if($sql !== false)
		{
			if(is_string($sql))
			{
				$this->_joins[] = $sql;
				return true;
			} else {
				return false;
			}
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