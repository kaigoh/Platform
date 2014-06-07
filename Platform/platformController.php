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
		if(file_exists("../vendor/autoload.php"))
		{
		    $this->_libraryLoader = require("../vendor/autoload.php");
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