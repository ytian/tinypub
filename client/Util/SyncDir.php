<?php
namespace Util;

class SyncDir {
    private $logger;
    private $config;
    public function __construct($config, $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }

    private function log($msg) {
        $this->logger->log($msg);
    }

    private function getOutFile($dir) {
        $fileName = "_sync_server.zip";
        return $dir . "/$fileName";
    }

    private function cleanFile($file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function zipDir($srcDir, $outFile) {
        $excludeCmd = "";
        foreach ($this->config['unsync_files'] as $path) {
            $excludeCmd .= "-x \"$path\" ";
        }
        $cmd = "cd $srcDir && zip -r $outFile . $excludeCmd";
        $this->log("[run_cmd] $cmd");
        exec($cmd, $out, $ret);
        if ($ret !== 0) {
            $this->log("[run_cmd_fail] $cmd");
        }
    }

    private function runSync($outFile, $serverInfo, $distFile) {
        $cmd = "scp $outFile {$serverInfo['user']}@{$serverInfo['host']}:$distFile";
        $this->log("[run_cmd] $cmd");
        exec($cmd, $out, $ret);
        if ($ret !== 0) {
            $this->log("[run_cmd_fail] $cmd");
        }
    }

    public function syncZip($srcDir, $host, $distFile) {
        $outFile = $this->getOutFile($srcDir);
        $this->cleanFile($outFile);
        $this->zipDir($srcDir, $outFile);
        $this->runSync($outFile, $host, $distFile);
        $this->cleanFile($outFile);
    }
}
