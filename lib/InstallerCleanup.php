<?php

if (!function_exists('rx_cleanup_installer')) {
    function rx_cleanup_installer(string $installerDir, string $trigger = 'installer'): bool
    {
        $rootDir = dirname($installerDir);
        $flagFile = $rootDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '.cleanup_failed';

        if ($trigger === 'config_bootstrap' && is_file($flagFile)) {
            if ((time() - (int) @filemtime($flagFile)) < 300) {
                return false;
            }
        }

        $clearFlag = static function () use ($flagFile): void {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
        };

        if (!is_dir($installerDir)) {
            $clearFlag();
            return true;
        }

        $indexFile = $installerDir . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexFile)) {
            if (!is_writable($indexFile)) {
                @chmod($indexFile, 0644);
            }
            @file_put_contents($indexFile, "<?php http_response_code(404); exit;\n");
        }

        if (!is_writable($rootDir)) {
            @chmod($rootDir, 0755);
        }

        $deleteRecursive = static function (string $dir) use (&$deleteRecursive): bool {
            if (!is_dir($dir)) {
                return true;
            }
            if (!is_writable($dir)) {
                @chmod($dir, 0755);
            }
            $items = @scandir($dir);
            if ($items === false) {
                return false;
            }
            $success = true;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }
                if (is_dir($path) && !is_link($path)) {
                    if (!$deleteRecursive($path) || !@rmdir($path)) {
                        $success = false;
                    }
                } elseif (!@unlink($path)) {
                    @chmod($path, 0644);
                    if (!@unlink($path)) {
                        $success = false;
                    }
                }
            }
            return $success;
        };

        $deleteRecursive($installerDir);
        clearstatcache(true, $installerDir);

        if (is_dir($installerDir)) {
            @rmdir($installerDir);
            clearstatcache(true, $installerDir);
        }

        if (!is_dir($installerDir)) {
            $clearFlag();
            return true;
        }

        $logsDir = dirname($flagFile);
        if (is_dir($logsDir) || @mkdir($logsDir, 0775, true)) {
            @touch($flagFile);
        }
        return false;
    }
}
