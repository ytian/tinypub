#!/usr/bin/env php
<?php
require __DIR__ . "/autoload.php";

class Logger {
    public function log($msg) {
        echo $msg . "\n";
    }
}

//tinypub client
class TinyPub {

    private $config = array();
    private $serverInfo = array();

    public static function __construct($config) {
        $this->config = $config;
        $this->logger = new Logger();
    }

    private function log($msg) {
        $this->logger->log($msg);
    }

    private function buildCmdInput($cmd, $args) {
        $arr = array(
            "cmd" => $cmd,
            "args" => $args,
        );
        $cmdStr = json_encode($arr);
        $authFile = $this->config['auth_file'];
        $authStr = file_exists($authFile) ? file_get_contents($authFile) : "";
        $userAuth = $_SERVER['USER'] . "|||" . $authStr;
        $cmdArr = array(
            $userAuth,
            $cmdStr,
        );
        return implode("\n", $cmdArr);
    }

    private function runServerCmd($cmd, $args) {
        $conf = $this->serverInfo;
        $serverPhp = $conf['base_dir'] . "/server/server.php";
        $cmdInput = $this->buildCmdInput($cmd, $args);
        $sshCmd = "ssh {$conf['user']}@{$conf['host']} {$conf['php_bin']} $serverPhp < $cmdInput";
        $this->log("[ssh_cmd] $sshCmd");
        exec($sshCmd, $out, $ret);
        if ($ret !== 0) {
            throw new \Exception("[ssh_error] " . implode("", $out));
        }
        $ret = implode("", $out);
        return json_decode($ret);
    }

    private function checkPublish($project, $tag) {
        $ret = $this->runServerCmd("check_publish", array("project" => $project, "tag" => $tag));
        if ($ret['code'] !== 0) {
            throw new \Exception("[check_publish_fail] {$ret['msg']}");
        }
    }

    private function checkProject($project) {
        if (!isset($this->config['projects'][$project])) {
            throw new \Exception("project($project) not set in config!");
        }
        $projectConf = $this->config['projects'][$project];
        $serverId = $projectConf['server'];
        if (!$serverId) {
            throw new \Exception("project($project) not set server!");
        }
        if (!isset($this->config['servers'][$serverId])) {
            throw new \Exception("server($serverId) not set!");
        }
        $this->serverInfo = $this->config['servers'][$serverId];
    }

    private function getUploadPath($project, $tag) {
        return "{$project}/{$tag}_" . date("Ymd-His");
    }

    //mkdir
    private function syncInit($uploadPath) {
        $ret = $this->runServerCmd("sync_init", array("upload_path" => $uploadPath));
        if ($ret['code'] !== 0) {
            throw new \Exception("[sync_init_fail] {$ret['msg']}");
        }
    }

    //unzip
    private function syncFinish($project, $tag, $uploadPath) {
        $ret = $this->runServerCmd("sync_finish", array("project" => $project, "tag" => $tag, "upload_path" => $uploadPath));
        if ($ret['code'] !== 0) {
            throw new \Exception("[sync_finish_fail] {$ret['msg']}");
        }
    }

    private function syncDir($srcDir, $project, $tag) {
        $uploadPath = $this->getUploadPath($projects, $tag);
        $this->syncInit($uploadPath);
        $info = $this->serverInfo;
        $sync = new Tool\SyncDir($this->logger);
        $syncPath = $info['base_dir'] . "/data/" . $uploadPath . ".zip";
        $sync->syncZip($srcDir, $info['host'], $syncPath);
        $this->syncFinish($project, $tag, $uploadPath);
    }

    private function startService($project, $tag) {
        $ret = $this->runServerCmd("sync_finish", array("project" => $project, "tag" => $tag));
        if ($ret['code'] !== 0) {
            throw new \Exception("[start_service_fail] {$ret['msg']}");
        }
    }

    //publish code
    public function publish($srcDir, $tag) {
        $project = basename($srcDir);
        $this->checkPublish($project);
        $this->checkAuth($project, $tag);
        $this->syncDir($srcDir, $project, $tag);
        $this->startService($project, $tag);
    }

    public function run() {

    }
}

$config = include __DIR__ . "/config.php";
$tinyPub = new TinyPub($config);
$tinyPub->run();