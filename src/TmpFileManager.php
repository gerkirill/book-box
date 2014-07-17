<?php

class TmpFileManager {
    private $tmpDir;
    private $createdFiles = array();

    public function __construct($tmpDir) {
        $this->tmpDir = rtrim($tmpDir, '/') . '/';
    }

    public function createFile($subpath, $prefix='tmp_') {
        $path = tempnam($this->tmpDir . $subpath, $prefix);
        $handle = fopen($path, 'w+b');
        $this->createdFiles[$path] = $handle;
        return [$path, $handle];
    }

    public function cleanup() {
        foreach($this->createdFiles as $path => $handle) {
            @fclose($handle);
            @unlink($path);
        }
    }
} 