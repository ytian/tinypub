<?php
require __DIR__ . "/autoload.php";

class Server {

    private $config = array();
    private $logger = array();
    private $userInfo = array();

    private static $METHOD_MAP = array(
        "check_publish" => "checkPublishCmd",
        "sync_finish" => "syncFinishCmd",
        "start_service" => "startServiceCmd",
    );

    public static function __construct($config) {
        $this->config = $config;
        $this->logger = new \Util\Logger();
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

    private function sendResponse($code, $msg, $data = false) {
        $arr = array();
        $arr['code'] = $code;
        $arr['msg'] = $msg;
        if ($data !== false) {
            $arr['data'] = $data;
        }
        echo json_encode($arr);
    }

    //check method auth
    private function checkAuth($method, $args) {
        $auth = new \Util\Auth($this->config);
        $auth->check($this->userInfo, $method, $args);
    }

    private function checkProject($project, $tag) {
        if (!$project) {
            throw new \Exception("param project is empty!");
        }
        if (!$tag) {
            throw new \Exception("param tag is empty!");
        }
        if (!isset($this->config['projects'][$project])) {
            throw new \Exception("project($project) is not set!");
        }
        $regex = "/^[0-9a-zA-Z\-_]$/";
        if (!preg_match($regex, $tag)) {
            throw new \Exception("tag($tag) format error!");
        }
    }

    private function mkDir($dir) {
        if (!file_exists($dir)) {
            $ret = mkdir($dir);
            if (!$ret) {
                throw new \Exception("mkdir($dir) failed!");
            }
        }
    }

    private function initProjectEnv($project) {
        $baseDir = $this->config['base_dir'];
        $dataDir = $baseDir . "/data/{$project}";
        $projectDir = $baseDir . "/project/{$project}";
        $this->mkDir($dataDir);
        $this->mkDir($projectDir);
    }

    //检查是否可以发布
    private function checkPublishCmd($args) {
        $this->checkProject($args['project'], $args['tag']);
        $this->initProjectEnv($args['project']);
        $this->sendResponse(0, '');
    }

    private function upzip($dataPath) {
        $uploadZip = $dataPath . ".zip";
        if (!file_exists($uploadZip)) {
            throw new \Exception("upload_zip($uploadZip) not exists!");
        }
        $cmd = "unzip $uploadZip -d $dataPath";
        exec($cmd, $out, $ret);
        if ($ret !== 0) {
            throw new \Exception("cmd($cmd) error!");
        }
    }

    private function createLink($project, $tag, $dataPath) {
        $linkFile = "{$this->config['base_dir']}/project/{$project}/{$tag}";
        exec("rm -rf $linkFile");
        exec("ln -s $dataPath $linkFile");
    }

    private function syncFinishCmd($args) {
        $project = $args['project'];
        $tag = $args['tag'];
        $this->checkProject($project, $tag);
        $dataPath = "{$this->config['base_dir']}/data/{$project}/{$args['tag_path']}";
        $this->unzip($dataPath);
        $this->createLink($project, $tag, $dataPath);
        $this->sendResponse(0, '');
    }

    private function startServiceCmd($args) {
        $project = $args['project'];
        $tag = $args['tag'];
    }

    public function run() {

    }
}

$config = include __DIR__ . "/config.php";
$server = new Server($config);
$server->run();
