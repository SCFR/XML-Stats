<?php
  require_once('item.php');

  Class SC_Engine extends SC_Item {

    protected $path;
    private $XML;

    function __construct($name) {
      parent::__construct($name);
    	global $_SETTINGS;
      $this->path = $_SETTINGS['STARCITIZEN']['scripts'].$_SETTINGS['STARCITIZEN']['PATHS']['engine'].$this->constructor."/".$name.".xml";

      $this->XML = simplexml_load_file($this->path);

      $this->get_stats($this->XML->params->param);
    }

    function get_infos() {
      return $this->params;
    }

  }

?>