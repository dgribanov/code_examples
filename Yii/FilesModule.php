<?php

class FilesModule extends CWebModule
{
    public static function createZipFromFolder($sourceDirPath, $zipFilePath, $zipDir = '')
    {
        // Create recursive directory iterator
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirPath, RecursiveDirectoryIterator::SKIP_DOTS));

        // Choose the flag for ZipArchive::open(filename, flag)
        $flags = array(ZIPARCHIVE::OVERWRITE, ZIPARCHIVE::CREATE);
        $flag = file_exists($zipFilePath)? $flags[0] : $flags[1];

        // Create the instance of ZipArchive class and open zip archive
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, $flag) !== true){
            return false;
        }

        //Use recursive directory iterator
        //$file is instance of class SplFileInfo
        foreach ($files as $name => $file) {
            // Get real path for current file
            $filePath = str_replace('\\', '/', $file->getRealPath());
            // Create relative file/directory structure for zip
            $relPath = $zipDir . str_replace(array('\\', $sourceDirPath), array('/', ''), $name);
            // Add current file to archive
            $zip->addFile($filePath, $relPath);
        }

        // Zip archive will be created only after closing object
        if($zip->close()){
            return true;
        }
        return false;
    }
}