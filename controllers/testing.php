<?php

class testing extends platformController {
	
	public function index()
	{
	    echo "Oogle, boogle...";
	}
    
    public function burger()
    {
        switch($this->_pageExtension)
        {
            case "page":
                echo "You said:<br />";
                foreach($this->_getParams as $param => $value)
                {
                    echo "&nbsp;&nbsp;&nbsp;<strong>".$param.":</strong> ".$value."<br />";
                }
                break;
            case "json":
                echo json_encode(array("HARDCORE_JSON" => true));
                break;
        }
    }
    
    public function getdump()
    {
        var_dump($_GET);
    }
    
    public function postdump()
    {
        var_dump($_POST);
    }
    
}