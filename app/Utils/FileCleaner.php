<?php

namespace App\Utils;

class FileCleaner
{
    /**
     * Remove arquivos e diretórios temporários específicos de /tmp.
     *
     * @return void
     */
    public static function cleanTemporaryFiles(): void
    {
        $txtFiles = glob('/tmp/*.txt');
        $zipFiles = glob('/tmp/*.zip');
        $cleanedFiles = glob('/tmp/cleaned*');
        $dirs = glob('/tmp/unzip*', GLOB_ONLYDIR);

        foreach (array_merge($txtFiles, $zipFiles, $cleanedFiles) as $file) {
            @unlink($file);
        }

        foreach ($dirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }


}
