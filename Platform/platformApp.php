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