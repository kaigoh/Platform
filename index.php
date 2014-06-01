<?php

	/**
	 * Routing
	*/
	
	// To emulate a GET request with URL
	// re-writing, we need to replace the
	// first & with a ?
	$requestRaw = preg_replace('/&/', '?', $_SERVER['QUERY_STRING'], 1);
	$request = explode("?", $requestRaw);
	
	// Set the default (index) route
	$defaultRoute = array(
		"controller" => "router",
		"function" => "index",
		"extension" => "html",
	);
	
	// Set the error (404) route
	$errorRoute = array(
		"controller" => "router",
		"function" => "error404",
		"extension" => "html",
	);
	
	// Process the route URL
	$routeRaw = pathinfo($request[0]);
	$route = array(
		"controller" => null,
		"function" => "index",
		"extension" => null,
	);
	if(strlen($routeRaw["basename"]) > 0)
	{
		if($routeRaw["dirname"] == ".")
		{
			$route["controller"] = strtolower($routeRaw["filename"]);
		} else {
			$route["controller"] = strtolower($routeRaw["dirname"]);
			$route["function"] = strtolower($routeRaw["filename"]);
		}
		if(isset($routeRaw["extension"]))
		{
			$route["extension"] = strtolower($routeRaw["extension"]);
		}
	} else {
		// No URL supplied, so set the default route
		$route = $defaultRoute;
	}
	
	// Process GET data
	$getParams = array();
	if(count($request) > 0)
	{
		// Split the GET params up
		$getRaw = explode("&", $request[1]);
		foreach($getRaw as $paramRaw)
		{
			$param = explode("=", $paramRaw);
			if(count($param) > 0)
			{
				$getParams[$param[0]] = $param[1];
			} else {
				$getParams[$param[0]] = null;
			}
		}
	}
	// Fix the $_GET array
	$_GET = $getParams;
	
	if(routeAndLoad($route) === false)
	{
		// Set 404 header
		header("HTTP/1.0 404 Not Found");
		if(routeAndLoad($errorRoute) === false)
		{
			echo "404 - Cannot route request. Also, the 404 error route was not found :(";
		}
	}
	
	// Load coresponding class and execute the matching function
	function routeAndLoad($route)
	{
		if(file_exists("controllers/".$route["controller"].".php"))
		{
			require("controllers/".$route["controller"].".php");
			$controllerName = $route["controller"];
			$pageName = $route["function"];
			$pageExtension = $route["extension"];
			$controller = new $controllerName($pageName, $pageExtension);
			if(method_exists($controller, $pageName))
			{
				$controller->$pageName();
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Base controller class
	*/
	class platformController {
		
	    protected $_pageName = null;
	    protected $_pageExtension = null;
	    
	    function __construct($pageName, $pageExtension)
	    {
	        $this->_pageName = $pageName;
	        $this->_pageExtension = $pageExtension;
	    }
		
	}