<?php

namespace Imanghafoori\LaravelMicroscope\SearchReplace;

use Imanghafoori\LaravelMicroscope\Foundations\PhpFileDescriptor;

class CachedFiles
{
    private static $cache = [];

    private function __construct()
    {
        //
    }

    public static function isCheckedBefore($patternKey, PhpFileDescriptor $file): bool
    {
        if (self::cacheIsLoaded($patternKey)) {
            return self::checkFile($patternKey, $file);
        }

        $path = self::getPathForPattern().$patternKey.'.php';

        // If there is no cache file:
        if (! file_exists($path)) {
            return false;
        }

        // If is not loaded but exists:
        self::loadFile($patternKey, $path);

        return self::checkFile($patternKey, $file);
    }

    private static function cacheIsLoaded($patternKey): bool
    {
        return isset(self::$cache[$patternKey]);
    }

    private static function getPathForPattern(): string
    {
        $ds = DIRECTORY_SEPARATOR;

        return storage_path('framework'.$ds.'cache'.$ds.'microscope'.$ds);
    }

    private static function checkFile($PatternKey, PhpFileDescriptor $file)
    {
        return $file->getFileName() === self::readFromCache($PatternKey, $file->getMd5());
    }

    private static function loadFile($patternKey, string $path): void
    {
        self::$cache[$patternKey] = require $path;
    }

    public static function addToCache($patternKey, PhpFileDescriptor $file)
    {
        self::$cache[$patternKey][$file->getMd5()] = $file->getFileName();
    }

    public static function writeCacheFiles()
    {
        if (! is_dir(self::getPathForPattern())) {
            mkdir(self::getPathForPattern(), 775);
        }

        foreach (self::$cache as $patternKey => $fileMd5) {
            file_put_contents(self::getPathForPattern().$patternKey.'.php', self::getCacheFileContents($fileMd5));
        }
    }

    private static function readFromCache($patternKey, $md5)
    {
        return self::$cache[$patternKey][$md5] ?? '';
    }

    private static function getCacheFileContents($fileHashes): string
    {
        return '<?php '.PHP_EOL.'return '.var_export($fileHashes, true).';';
    }
}