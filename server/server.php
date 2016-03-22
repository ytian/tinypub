<?php
require __DIR__ . "/autoload.php";

class Logger {
    public function log($msg) {
        echo $msg . "\n";
    }
}

class Auth {
}

class Server {

    private $config = array();
    private $logger = array();
    private $userInfo = array();

    private static $METHOD_MAP = array(
        "check_auth" => "checkAuth",
        "sync_init" => "syncInit",
        "sync_finish" => "syncFinish",
    );

    public static function __construct($config) {
        $this->config = $config;
        $this->logger = new Logger();
    }

    private function log($msg) {
        $this->logger->log($msg);
    }

    private function getCmdInput() {
        $content = stream_get_contents(STDIN);
        return $content;
    }

    private function loginUser($authLine) {
        list($username, $key) = explode("|||", $authLine);
        $arr = explode(" ", $_SERVER['SSH_CLIENT']);
        $clientIp = $arr[0];
        $userInfo = array(
            "username" => $username,
            "key" => $key,
            "client_ip" => $clientIp,
        );
        $this->userInfo = $userInfo;
    }

    private function processCmd() {
        $cmdInput = $this->getCmdInput();
        if (!$cmdInput) {
            throw new \Exception("cmd input is empty!");
        }
        list($authLine, $cmdLine) = explode("\n", $cmdInput, 2);
        $cmdInfo = json_decode($cmdLine, true);
        if (!$cmdInfo) {
            throw new \Exception("cmd format error!");
        }
        $cmd = $cmdInfo['cmd'];
        $args = $cmdInfo['args'];
        if (!isset(self::$METHOD_MAP[$cmd])) {
            throw new \Exception("method($cmd) not exists!");
        }
        $method = self::$METHOD_MAP[$cmd];
        $this->loginUser($authLine);
        $this->checkAuth($method, $args);
        call_user_func(array($this, $method), $args);
    }

    private function checkAuth($method, $args) {

    }

    public function run() {

    }
}

$config = include __DIR__ . "/config.php";
$server = new Server($config);
$server->run();
