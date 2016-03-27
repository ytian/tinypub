#!/usr/bin/env php
<?php
require __DIR__ . "/autoload.php";
date_default_timezone_set("Asia/Shanghai");

//tinypub client
class TinyPub {

    private $config = array();
    private $serverInfo = array();

    public function __construct($config) {
        $this->config = $config;
        $this->logger = new \Util\Logger($config['log_file']);
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
        $cmdInput = implode("\n", $cmdArr);
        $cmdStr = base64_encode($cmdInput);
        return $cmdStr;
    }

    private function runServerCmd($sid, $cmd, $args) {
        $conf = $this->getServerInfoBySid($sid);
        $serverPhp = $conf['base_dir'] . "/server/server.php";
        $cmdInput = $this->buildCmdInput($cmd, $args);
        $sshCmd = "ssh {$conf['user']}@{$conf['host']} {$conf['php_bin']} $serverPhp $cmdInput 2>&1";
        $this->log("[server_cmd] $sshCmd");
        exec($sshCmd, $out, $ret);
        if ($ret !== 0) {
            throw new \Exception("[server_cmd_fail] " . implode("", $out));
        }
        $content = implode("", $out);
        $ret = json_decode($content, true);
        if (!$ret) {
            throw new \Exception("[server_cmd_error] server_out: $content");
        }
        return $ret;
    }

    private function getServerInfoBySid($sid) {
        if (!isset($this->config['servers'][$sid])) {
            throw new \Exception("server($sid) not exists!");
        }
        return $this->config['servers'][$sid];
    }

    private function getSidByProject($project) {
        if (!isset($this->config['projects'][$project])) {
            throw new \Exception("project($project) not exists!");
        }
        return $this->config['projects'][$project]['server'];
    }

    private function checkPublish($project, $tag) {
        $sid = $this->getSidByProject($project);
        $ret = $this->runServerCmd($sid, "check_publish", array("project" => $project, "tag" => $tag));
        if ($ret['code'] !== 0) {
            throw new \Exception("[check_publish_fail] {$ret['msg']}");
        }
        return $ret['data'];
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

    //unzip
    private function syncFinish($project, $tag, $tagPath) {
        $sid = $this->getSidByProject($project);
        $ret = $this->runServerCmd($sid, "sync_finish", array("project" => $project, "tag" => $tag, "tag_path" => $tagPath));
        if ($ret['code'] !== 0) {
            throw new \Exception("[sync_finish_fail] {$ret['msg']}");
        }
    }

    private function syncDir($srcDir, $project, $tag, $syncInfo) {
        $tagPath = $syncInfo['tag_path'];
        $info = $this->serverInfo;
        $sync = new \Util\SyncDir($this->config, $this->logger);
        $syncPath = $info['base_dir'] . "/data/{$project}/{$tagPath}.zip";
        $sync->syncZip($srcDir, $info, $syncPath);
        $this->syncFinish($project, $tag, $tagPath);
    }

    private function startService($project, $tag) {
        $sid = $this->getSidByProject($project);
        $ret = $this->runServerCmd($sid, "start_service", array("project" => $project, "tag" => $tag));
        if ($ret['code'] !== 0) {
            throw new \Exception("[start_service_fail] {$ret['msg']}");
        }
        return $ret['data'];
    }

    private function sendMsg($msg) {
        echo $msg . "\n";
    }

    //publish code
    public function publish($srcDir, $tag) {
        $project = basename($srcDir);
        $this->sendMsg("[publish_prepare] project: $project, tag: $tag, src_dir: $srcDir");
        $this->checkProject($project);
        $syncInfo = $this->checkPublish($project, $tag);
        $this->sendMsg("[sync_dir] $srcDir");
        $this->syncDir($srcDir, $project, $tag, $syncInfo);
        $this->sendMsg("[start_service] {$project}.{$tag}");
        $serviceInfo = $this->startService($project, $tag);
        $this->sendMsg("service({$project}.{$tag}) is ok!");
        $this->sendMsg("HOST: {$serviceInfo['host']}");
    }

    private function parseCmd() {
        global $argv;
        $args = array_slice($argv, 1);
        $cmd = array_shift($args);
        $cmd = $this->getRealCmd($cmd);
        return array($cmd, $args);
    }

    private function getRealCmd($cmd) {
        if (!$cmd || $cmd == '-h') {
            return "help";
        }
        return $cmd;
    }

    private function psAction($args) {
        $arr = array();
        $arr[] = array("SERVER_NAME", "TAG", "DOMAIN", "PORT", "STATUS");
        foreach ($this->config['servers'] as $sid => $conf) {
            $ret = $this->runServerCmd($sid, "list_services", array());
            if ($ret['code'] !== 0) {
                throw new \Exception("[ps_fail] {$ret['msg']}");
            }
            foreach ($ret['data'] as $service) {
                $str = "$sid({$conf['host']})";
                $arr[] = array($str, $service['name'], $service['domain'], $service['port'], $service['status']);
            }
        }
        $this->dispArr($arr);
    }

    private function dispArr($arr) {
        foreach ($arr as $item) {
            if (is_array($item)) {
                echo implode("\t", $item) . "\n";
            } else {
                echo $item . "\n";
            }
        }
    }

    private function rmAction($args) {
        $project = $args[1];
        $tag = $args[2];
    }

    private function pubAction($args) {
        $tag = $args[0];
        $srcDir = getcwd();
        $this->publish($srcDir, $tag);
    }

    private function helpAction() {
        $lines = array();
        $lines[] = "tinypub [cmd] [arg1] [arg2] ...";
        $lines[] = "pub [tag_name] --- publish project";
        $lines[] = "ps --- display all services";
        $this->dispArr($lines);
    }

    private function noExistAction($args) {
        $this->log("action($args[0]) not exists!");
    }

    private function runCmd($cmd, $args) {
        $action = $cmd . "Action";
        if (!method_exists($this, $action)) {
            $args = array($action);
            $action = "noExistAction";
        }
        $this->$action($args);
    }

    public function run() {
        try {
            list($cmd, $args) = $this->parseCmd();
            $this->runCmd($cmd, $args);
        } catch (\Exception $e) {
            $this->log("[error] " . $e->getMessage());
            echo "[error] " . $e->getMessage() . "\n";
        }
    }
}

$config = include __DIR__ . "/config.php";
$tinyPub = new TinyPub($config);
$tinyPub->run();
