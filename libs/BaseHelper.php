<?php
namespace F3CMS;

class BaseHelper {

    protected $_db;

    //! Instantiate class
    function __construct() {
        $this->_db = \Base::instance()->get('DB');
    }
}
