<?php
namespace Util;
//check user auth
class Auth {
    private $config;
    public function __construct($config) {
        $this->config = $config;
    }

    private function checkTagAuth($userInfo, $tag) {
        $conf = $this->config['reserve_tag'];
        if (in_array($tag, $conf['tags']) && $userInfo['key'] != $conf['auth']) {
            throw new AuthException("tag($tag) not allow to use!");
        }
    }

    public function check($userInfo, $method, $args) {
        if (isset($args['tag'])) {
            $this->checkTagAuth($userInfo, $args['tag']);
        }
    }
}
