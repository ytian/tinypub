<?php
namespace Util;
class DockerCtrl {
    private $config;
    public function __construct($config) {
        $this->config = $config;
    }

    public function startService($project, $tag) {
        $projectConf = $this->config['projects'][$project];
        $imageName = $projectConf['image_name'];
        $containerName = "{$project}.{$tag}";
        return $this->startWebContainer($containerName, $imageName);
    }

    private function startWebContainer($name, $imageName) {
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
        $this->doStartWebContainer($name, $imagesName, $port);
        return $port;
    }

    private function choosePort($containers) {
        $maxPort = $this->config['port_min'];
        foreach ($containers as $container) {
            if ($container['port'] > $maxPort) {
                $maxPort = $container['port'];
            }
        }
        return $maxPort + 1;
    }

    private function doStartWebContainer($name, $imagesName, $port) {
        $cmd = "sudo docker run -d --name $name -p $port:80 $imageName";
        exec($cmd);
    }

    private function stopContainer($name) {
        $cmd = "sudo docker stop $name";
        exec($cmd);
    }

    private function rmContainer($name) {
        $cmd = "sudo docker rm $name";
        exec($cmd);
    }

    private function getAllImages() {
        $cmd = "sudo docker images";
        exec($cmd, $out, $ret);
        $images = array();
        array_shift($out);
        foreach ($out as $line) {
            $arr = preg_split("/\s+/", $line);
            $images[] = $arr[0];
        }
        return $images;
    }

    private function extractPort($str) {
        $regex = "/0.0.0.0:(\d+)->80/";
        preg_match($regex, $str, $m);
        if (!$m) {
            return "";
        }
        return $m[1];
    }

    private function getAllContainers() {
        $cmd = "sudo docker ps -a";
        exec($cmd, $out, $ret);
        $containers = array();
        array_shift($out);
        foreach ($out as $line) {
            $arr = preg_split("/\s+/", $line);
            $name = $arr[6];
            list($status, $left) = explode(" ", $arr[4]);
            $port = $this->extractPort($arr[5]);
            $containers[$name] = array(
                "status" => strtolower($status),
                "port" => $port,
            )
        }
        return $containers;
    }

}
