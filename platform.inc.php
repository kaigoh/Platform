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
	
	// Request data
	private $requestRaw = null;
	private $request = null;
	
	// URL segments
	private $urlSegments = array();
	
	// GET data
	private $getParams = array();
	
	// Routes
	private $routeRaw = null;
	
	private $defaultRoute = array(
		"controller" => "defaultRoutes",
		"function" => "index",
		"extension" => null,
	);
	
	private $errorRoute = array(
		"controller" => "defaultRoutes",
		"function" => "error404",
		"extension" => null,
	);
	
	private $route = array(
		"controller" => null,
		"function" => "index",
		"extension" => null,
	);
	
	function __construct($defaultRoute = false, $errorRoute = false)
	{
	    // Override the default route if supplied
	    if($defaultRoute !== false && is_array($defaultRoute))
	    {
	        $this->defaultRoute = $defaultRoute;
	    }
	    // Override the error route if supplied
	    if($errorRoute !== false && is_array($errorRoute))
	    {
	        $this->errorRoute = $errorRoute;
	    }	    
		// Process the route URL
		$this->requestRaw = preg_replace('/&/', '?', $_SERVER['QUERY_STRING'], 1);
		$this->request = explode("?", $this->requestRaw);
		$this->routeRaw = pathinfo($this->request[0]);
		
		// Process the URL segments
		$this->urlSegments = explode("/", strtolower($this->routeRaw["dirname"]));
		
		if(strlen($this->routeRaw["basename"]) > 0)
		{
			if($this->routeRaw["dirname"] == ".")
			{
				$this->route["controller"] = strtolower($this->routeRaw["filename"]);
			} else {
				$this->route["controller"] = $this->urlSegments[0];
				$this->route["function"] = strtolower($this->routeRaw["filename"]);
			}
			if(isset($this->routeRaw["extension"]))
			{
				$this->route["extension"] = strtolower($this->routeRaw["extension"]);
			}
		} else {
			// No URL supplied, so set the default route
			$this->route = $this->defaultRoute;
		}
		
		// Process GET data
		$this->getParams = array();
		if(count($this->request) > 0)
		{
			// Split the GET params up
			$getRaw = explode("&", $this->request[1]);
			foreach($getRaw as $paramRaw)
			{
				$param = explode("=", $paramRaw);
				if(count($param) > 0)
				{
					$this->getParams[$param[0]] = $param[1];
				} else {
					$this->getParams[$param[0]] = null;
				}
			}
		}
		// Fix the $_GET array
		$_GET = $this->getParams;
		
		if($this->routeAndLoad($this->route) === false)
		{
			// Set 404 header
			header("HTTP/1.0 404 Not Found");
			if($this->routeAndLoad($this->errorRoute) === false)
			{
				echo "404 - Cannot route request. Also, the 404 error route was not found :(";
			}
		}
		
	}
	
	// Load coresponding class and execute the matching function
	private function routeAndLoad($route)
	{
		if(file_exists("controllers/".$route["controller"].".php"))
		{
			require("controllers/".$route["controller"].".php");
			
			// Some page variables
			$controllerName = $route["controller"];
			$pageName = $route["function"];
			$requestMethodPageName = $_SERVER['REQUEST_METHOD'].$pageName;
			$pageExtension = $route["extension"];
			$pageNameExtension = $pageName.$pageExtension;
			$requestMethodPageNameExtension = $_SERVER['REQUEST_METHOD'].$pageName.$pageExtension;
			$extensionHandler = "handler".$pageExtension;
			
			// Instantiate the correct model
			$controller = new $controllerName($this->requestRaw, array_slice($this->urlSegments, 1), $_SERVER['REQUEST_METHOD'], $pageName, $pageExtension);
			
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
 * Base controller class
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