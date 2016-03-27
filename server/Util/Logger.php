<?php
namespace Util;

class Logger {
    private $logFile;
    public function __construct($logFile) {
        $this->logFile = $logFile;
    }

    public function log($msg) {
        $line = "[". date("Y-m-d H:i:s") . "]" . $msg . "\n";
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
