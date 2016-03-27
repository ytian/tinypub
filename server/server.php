<?php
require __DIR__ . "/autoload.php";
date_default_timezone_set("Asia/Shanghai");

class Server {

    private $config = array();
    private $logger = array();
    private $userInfo = array();

    private static $METHOD_MAP = array(
        "check_publish" => "checkPublishCmd",
        "sync_finish" => "syncFinishCmd",
        "start_service" => "startServiceCmd",
        "list_services" => "listServicesCmd",
    );

    public function __construct($config) {
        $this->config = $config;
        $this->logger = new \Util\Logger($config['log_file']);
    }

    private function log($msg) {
        $this->logger->log($msg);
    }

    private function getCmdInput() {
        global $argv;
        $cmdStr = $argv[1];
        if (!$cmdStr) {
            throw new \Exception("cmd input is empty!");
        }
        $content = base64_decode($cmdStr);
        return $content;
    }

    private function loginUser($authLine) {
        if (strpos($authLine, '|||') === false) {
            throw new \Exception("cmd(auth_line) format error!");
        }
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
        $this->log("[login_user] " . json_encode($this->userInfo));
        $this->log("[server_cmd_start] $cmd|$method|" . json_encode($args));
        call_user_func(array($this, $method), $args);
        $this->log("[server_cmd_finish] $cmd");
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
        $regex = "/^[0-9a-zA-Z\-_]+$/";
        if (!preg_match($regex, $tag)) {
            throw new \Exception("tag($tag) format error!");
        }
    }

    private function mkDir($dir) {
        if (!file_exists($dir)) {
            $this->log("[mkdir] $dir");
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

    private function getTagPath($tag) {
        return $tag . "." . date("Ymd-His");
    }

    //检查是否可以发布
    private function checkPublishCmd($args) {
        $project = $args['project'];
        $tag = $args['tag'];
        $this->checkProject($project, $tag);
        $this->initProjectEnv($project);
        $tagPath = $this->getTagPath($tag);
        $this->sendResponse(0, '', array("tag_path" => $tagPath));
    }

    private function unzip($dataPath) {
        $uploadZip = $dataPath . ".zip";
        if (!file_exists($uploadZip)) {
            throw new \Exception("upload_zip($uploadZip) not exists!");
        }
        $cmd = "unzip $uploadZip -d $dataPath";
        $this->runCmd($cmd, true);
        $this->cleanFile($uploadZip);
    }

    private function cleanFile($file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function createLink($project, $tag, $dataPath) {
        $linkFile = "{$this->config['base_dir']}/project/{$project}/{$tag}";
        $this->runCmd("rm -rf $linkFile");
        $this->runCmd("ln -s $dataPath $linkFile");
    }

    private function syncFinishCmd($args) {
        $project = $args['project'];
        $tag = $args['tag'];
        $this->checkProject($project, $tag);
        $dataPath = "{$this->config['base_dir']}/data/{$project}/{$args['tag_path']}";
        $this->unzip($dataPath);
        $this->createLink($project, $tag, $dataPath);
        $this->cleanHistoryData($project, $tag);
        $this->sendResponse(0, '');
    }

    private function getDelDirs($dataDir, $tag) {
        $dirs = glob($dataDir . "/{$tag}.*");
        rsort($dirs);
        $historyNum = $this->config['history_limit'];
        $delDirs = array_slice($dirs, $historyNum);
        return $delDirs;
    }

    private function cleanHistoryData($project, $tag) {
        $dataDir = "{$this->config['base_dir']}/data/{$project}";
        $dirs = $this->getDelDirs($dataDir, $tag);
        foreach ($dirs as $dir) {
            $this->runCmd("rm -rf $dir");
        }
    }

    private function addNginxConf($project, $tag, $testHost, $port) {
        $baseDir = $this->config['nginx_include_dir'];
        $confDir = $baseDir . "/{$project}";
        $this->mkDir($confDir);
        $nginxConfFile = $confDir . "/{$tag}.conf";
        $nginxConfig = new \Util\NginxConfig($this->config);
        $confContent = $nginxConfig->getConfig($project, $tag, $testHost, $port);
        file_put_contents($nginxConfFile, $confContent);
    }

    private function getDockerCtrl() {
        $dockerCtrl = new \Util\DockerCtrl($this->config, $this->logger);
        return $dockerCtrl;
    }

    private function startDockerService($project, $tag) {
        $dockerCtrl = $this->getDockerCtrl();
        $port = $dockerCtrl->startService($project, $tag);
        return $port;
    }

    private function runCmd($cmd, $check = false) {
        $this->log("[run_cmd] " . $cmd);
        exec($cmd, $out, $ret);
        if ($check && $ret !== 0) {
            throw new \Exception("[cmd_error] " . implode("", $out));
        }
    }

    private function restartNginx() {
        $cmd = "sudo {$this->config['nginx_bin']} -s reload";
        $this->runCmd($cmd);
    }

    private function getTestHost($project, $tag) {
        $baseDomain = $this->config['base_domain'];
        $testHost = "{$tag}.{$project}.{$baseDomain}";
        return $testHost;
    }

    private function startServiceCmd($args) {
        $project = $args['project'];
        $tag = $args['tag'];
        $testHost = $this->getTestHost($project, $tag);
        $port = $this->startDockerService($project, $tag);
        if ($port == -1) { //already exists
            $this->log("service($project:$tag) already started!");

        } else {
            $this->addNginxConf($project, $tag, $testHost, $port);
            $this->restartNginx();
        }
        $this->sendResponse(0, '', array("host" => $testHost));
    }

    private function listServicesCmd($args) {
        $dockerCtrl = $this->getDockerCtrl();
        $containerMap = $dockerCtrl->getAllContainers();
        $serviceList = array();
        $names = array();
        $baseDomain = $this->config['base_domain'];
        foreach ($containerMap as $name => $info) {
            if ($info['port'] == 0) {
                continue;
            }
            list($project, $port) = explode(".", $name);
            $info['name'] = $name;
            $info['domain'] = $this->getTestHost($project, $port);
            $serviceList[] = $info;
            $names[] = $name;
        }
        array_multisort($names, SORT_STRING, SORT_ASC, $serviceList);
        $this->sendResponse(0, '', $serviceList);
    }

    public function run() {
        try {
            $this->processCmd();
        } catch (\Exception $e) {
            $this->sendResponse(1, $e->getMessage());
        }
    }
}

$config = include __DIR__ . "/config.php";
$server = new Server($config);
$server->run();
