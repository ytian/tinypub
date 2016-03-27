<?php
namespace Util;

class NginxConfig {
    private $config;
    public static $CONF_TPL = <<<TPL
server {
    listen       80;
    server_name  %test_host%;

    access_log  logs/docker_proxy.access.log;

    location / {
        proxy_pass http://127.0.0.1:%port%;
        proxy_set_header Host %real_host%;
        proxy_set_header X-Real-IP  \$remote_addr;
        proxy_set_header X-Forwarded-For \$remote_addr;
    }
}
TPL;
    public function __construct($config) {
        $this->config = $config;
    }

    public function getConfig($project, $tag, $testHost, $port) {
        $realHost = $this->config['projects'][$project]['host'];
        $from = array("%test_host%", "%real_host%", "%port%");
        $to = array($testHost, $realHost, $port);
        return str_replace($from, $to, self::$CONF_TPL);
    }
}
