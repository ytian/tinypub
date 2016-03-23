<?php
namespace Util;
//check user auth
class Auth {
    private $config;
    public static function __construct($config) {
        $this->config = $config;
    }

    public function check($userInfo, $method, $args) {
        
    }
}
