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

use Platform;

class testing extends Platform\platformController {

	/**
	 * You MUST include an "index" function in your
	 * classes. This way the router can find something
	 * to send your user to if all else fails
	 */
	public function index()
	{
	    var_dump($this->urlSegments);
	}

    /**
     * This is an example of the most basic
     * page handler. It will be accessible
     * using any method and any "extension".
     * Note that is is a fall back method,
     * ie. if the request is a POST and
     * a method called postBurger exists,
     * it would win over a method called burger.
     */
    public function burger()
    {
        // This shows another way of handling
        // page (emulated file-type) extensions
        // using the $this->_pageExtension
        // variable available when you extend
        // the platformController class
        switch($this->_pageExtension)
        {
            case "json":
                echo json_encode(array("HARDCORE_JSON" => true));
                break;
            default:
                echo "You said:<br />";
                foreach($_GET as $param => $value)
                {
                    echo "&nbsp;&nbsp;&nbsp;<strong>".$param.":</strong> ".$value."<br />";
                }
                break;
        }
    }

    /**
     * File handler for "ASP" extensions
     * (When creating your own "file"
     * handler, follow the convention
     * handlerEXTENSION - this is really
     * useful for setting MIME headers)
     */
    public function handlerASP()
    {
        // LOL
        header("X-Powered-By: ASP.NET");
    }

    /**
     * This method is called if
     * the file extension is asp
     */
    public function burgerASP()
    {
        echo "Haha, not really!";
    }

    /**
     * This method would be called
     * if the server received a post
     * request for testing/burger
     */
    public function postBurger()
    {
        echo "You made a POST request!";
    }

    /**
     * This method would be called
     * if the server received a post
     * request for testing/burger.json.
     * Note that the mime type headers
     * for JSON are sent automatically.
     * (See handlerASP()
     * for an example that you can override
     * in your controllers)
     */
    public function postBurgerJSON()
    {
        echo json_encode(array("method" => "post", "document" => "json", "verycool" => true));
    }

    /**
     * Similar to the above method,
     * but will respond to ANY method
     * that is not overridden elsewhere
     */
    public function burgerJSON()
    {
        echo json_encode(array("method" => strtolower($this->pageRequestMethod), "document" => "json", "verycool" => true));
    }

    /**
     * This method would be called
     * if the server received a put
     * request for testing/burger
     */
    public function putBurger()
    {
        echo "You made a PUT request!";
    }

	/**
	 * Testing custom routes
	 */
    public function echourl()
	{
		echo $this->rawRequest." was routed to testing/echourl!";
	}

	/**
	 * Advanced custom routes
	 */
	public function jamJSON()
	{
		echo json_encode(array("flavour" => str_replace("jam/flavour.json", "", $this->rawRequest)));
	}

}
