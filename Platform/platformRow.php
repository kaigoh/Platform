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
 * Platform database row class
 */
class platformRow {

	private $_database = false;
	private $_table = false;
	private $_tableColumns = false;
	private $_rowData = array();
	private $_rowDataOriginal = array();
	private $_newRow = false;

	function __construct(&$platformDatabase = false, &$platformTable = false, $newRow = false)
	{
		if($platformDatabase !== false && $platformTable !== false)
		{
			$this->_database = $platformDatabase;
			$this->_table = $platformTable;
			$this->_tableColumns = $this->_table->getColumns();
			if($newRow)
			{
				$this->_newRow = $newRow;
			}
		} else {
			throw new platformException("Unable to create platformRow object");
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
			if(in_array($columnName, $this->_tableColumns))
			{
				$this->_rowData[$columnName] = $columnValue;
			} else {
				throw new platformException("Column you are trying to set (".$columnName.") does not exist in ".$this->_table->getTableName()." table.");
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

	/**
	 * Fetch a column that has characters in
	 * it's name that are not valid in PHP
	 * variable names
	 */
	public function getColumn($columnName = false)
	{
		if($columnName !== false)
		{
			if(array_key_exists($columnName, $this->_rowData))
			{
				return $this->_rowData[$columnName];
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Fetch a column that has characters in
	 * it's name that are not valid in PHP
	 * variable names
	 */
	public function setColumn($columnName = false, $columnValue)
	{
		if($columnName !== false)
		{
			if(array_key_exists($columnName, $this->_rowData))
			{
				$this->_rowData[$columnName] = $columnValue;
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Is this a new row that is ready to be
	 * inserted? Also, once the row has been
	 * inserted, allows the platformTable
	 * class to toggle the platformRow status
	 * and update the primary key based on
	 * the insert ID.
	 */
	public function isNewRow($newRow = false)
	{
		if($newRow === false)
		{
			return $this->_newRow;
		} else {
			if(is_array($newRow))
			{
				if($this->_table->getPrimaryKey() !== false)
				{
					$primaryKey = $newRow["column"];
					$this->$primaryKey = $newRow["value"];
				}
				$this->_rowDataOriginal = $this->_rowData;
				$this->_newRow = false;
			}
		}
	}

}