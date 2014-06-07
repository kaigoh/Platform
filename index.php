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

require("Platform.php");

/**
 * This will create a basic Platform App.
 * You can override the default route
 * and the error routes by passing them as
 * parameters to the constructor, as below,
 * or you can not pass them to use the default
 * routes.
 */

 /**
  * Routes are stored as arrays.
  * controller: Specifies the class to load
  * function: Specifies the method (or "page")
  * extension: Specifies the extension (ie. JSON) - Can be null
  */
 $defaultRoute = array(
	"controller" => "defaultRoutes",
	"method" => "index",
	"extension" => null,
);

$errorRoute = array(
	"controller" => "defaultRoutes",
	"method" => "error404",
	"extension" => null,
);

// Instantiate Platform
$platformApp = new Platform\platformApp($defaultRoute, $errorRoute);

/**
 * Add any custom routes you need BEFORE running your application...
 * addRoute(URL to match, Controller class, Controller method, Request Method to match)
 */
$platformApp->addRoute("icecream/*", "testing", "echourl", "GET");
$platformApp->addRoute("icecream/*", "testing", "burger", "POST");
$platformApp->addRoute("lollypop/*/*/*/popsicle.*", "testing", "echourl", "*");
$platformApp->addRoute("chocolate/*/yummy.htm", "testing", "echourl", "POST");
$platformApp->addRoute("*jam/flavour.json", "testing", "jam", "POST");

// And away we go!
$platformApp->runApplication();
