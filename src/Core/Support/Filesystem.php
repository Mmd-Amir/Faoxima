<?php

namespace Faoxima\Core\Support;

final class Filesystem
{
    public static function deleteDirectory($directory)
    {
        if (!file_exists($directory)) {
            return true;
        }

        if (!is_dir($directory)) {
            return @unlink($directory);
        }

        $items = scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!self::deleteDirectory($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($directory);
    }

    public static function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            return false;
        }

        $items = scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                if (!self::copyDirectoryContents($sourcePath, $destinationPath)) {
                    return false;
                }
            } else {
                if (!@copy($sourcePath, $destinationPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function deleteFolder($folderPath)
    {
        if (!is_dir($folderPath))
            return false;

        $files = array_diff(scandir($folderPath), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                self::deleteFolder($filePath);
            } else {
                unlink($filePath);
            }
        }

        return rmdir($folderPath);
    }
}
