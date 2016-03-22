<?php
class SyncDir {
    private $logger = null;
    public function __construct($logger = null) {
        $this->logger = $logger;
    }

    private function log($msg) {
        if ($this->logger) {
            $this->logger->log($msg);
        }
    }

    public function syncZip($srcDir, $host, $distFile) {

    }
}
