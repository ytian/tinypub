<?php
namespace Util;
class DockerCtrl {
    private $config;
    private $logger;
    public function __construct($config, $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }

    private function log($msg) {
        $this->logger->log($msg);
    }

    public function startService($project, $tag) {
        $projectConf = $this->config['projects'][$project];
        $imageName = $projectConf['image_name'];
        $containerName = "{$project}.{$tag}";
        $srcDir = $this->config['base_dir'] . "/project/{$project}/{$tag}/";
        $targetDir = $this->config['projects'][$project]['map_dir'] . "/";
        $vMap = $srcDir . ":" . $targetDir;
        return $this->startWebContainer($containerName, $imageName, $vMap);
    }

    private function startWebContainer($name, $imageName, $vMap) {
        $allImages = $this->getAllImages();
        if (!in_array($imageName, $allImages)) {
            throw new \Exception("image($imageName) not exists!");
        }
        $allContainers = $this->getAllContainers();
        if (isset($allContainers[$name])) {
            if ($allContainers[$name]['status'] == 'up') {
                return -1;
            } else {
                $this->rmContainer($name);
            }
        }
        $port = $this->choosePort($allContainers);
        $pMap = "{$port}:80";
        $this->doStartWebContainer($name, $imageName, $pMap, $vMap);
        return $port;
    }

    private function isPortBusy($port) {
        $lsofBin = $this->config['lsof_bin'];
        $cmd = "sudo $lsofBin -i:$port";
        exec($cmd, $out, $ret);
        $this->log("[lsof_cmd] $cmd|$ret");
        if ($ret === 0) {
            return true;
        }
        return false;
    }

    private function choosePort($containers) {
        $maxPort = $this->config['port_min'];
        foreach ($containers as $container) {
            if ($container['port'] > $maxPort) {
                $maxPort = $container['port'];
            }
        }
        $validPort = $maxPort + 1;
        while ($this->isPortBusy($validPort)) {
            $validPort += 1;
        }
        return $validPort;
    }

    private function doStartWebContainer($name, $imageName, $pMap, $vMap) {
        $cmd = "sudo docker run -d --name $name -p $pMap -v $vMap $imageName";
        $this->log("[docker_cmd] $cmd");
        exec($cmd);
    }

    private function stopContainer($name) {
        $cmd = "sudo docker stop $name";
        $this->log("[docker_cmd] $cmd");
        exec($cmd);
    }

    private function rmContainer($name) {
        $cmd = "sudo docker rm -f $name";
        $this->log("[docker_cmd] $cmd");
        exec($cmd);
    }

    private function getAllImages() {
        $cmd = "sudo docker images";
        exec($cmd, $out, $ret);
        $images = array();
        array_shift($out);
        foreach ($out as $line) {
            $arr = preg_split("/\s\s+/", $line);
            $images[] = $arr[0] . ":" . $arr[1];
        }
        return $images;
    }

    private function extractPort($str) {
        if (!$str) {
            return 0;
        }
        $regex = "/0.0.0.0:(\d+)->80/";
        preg_match($regex, $str, $m);
        if (!$m) {
            return 0;
        }
        return $m[1];
    }

    private function extractContainerInfo($arr) {
        $statusStr = $arr[4];
        if (isset($arr[6])) {
            $name = $arr[6];
            $portStr = $arr[5];
        } else {
            $name = $arr[5];
            $portStr = "";
        }
        return array($statusStr, $portStr, $name);
    }

    public function getAllContainers() {
        $cmd = "sudo docker ps -a";
        exec($cmd, $out, $ret);
        $containers = array();
        array_shift($out);
        foreach ($out as $line) {
            $arr = preg_split("/\s\s+/", $line);
            list($statusStr, $portStr, $name) = $this->extractContainerInfo($arr);
            $arr = explode(" ", $statusStr);
            $status = $arr[0];
            $port = $this->extractPort($portStr);
            $containers[$name] = array(
                "status" => strtolower($status),
                "port" => $port,
            );
        }
        return $containers;
    }

}
