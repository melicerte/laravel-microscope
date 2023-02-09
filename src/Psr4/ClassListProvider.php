<?php

namespace Imanghafoori\LaravelMicroscope\Psr4;

use ImanGhafoori\ComposerJson\ComposerJson as Compo;
use Imanghafoori\LaravelMicroscope\FileReaders\FilePath;
use Imanghafoori\TokenAnalyzer\GetClassProperties;
class ClassListProvider
{
    public static $checkedNamespacesStats = [];

    public static $buffer = 600;

    public function getClasslists(array $autoloads, ?\Closure $onCheck, $folder)
    {
        $classLists = [];
        foreach (Compo::purgeAutoloadShortcuts($autoloads) as $path => $autoload) {
            $classLists[$path] = [];
            foreach ($autoload as $namespace => $psr4Path) {
                $classes = $this->getClassesWithin($psr4Path, $onCheck, $folder);
                self::$checkedNamespacesStats[$namespace] = count($classes);
                $classLists[$path] = array_merge(
                    $classLists[$path],
                    $classes
                );
            }
        }

        return $classLists;
    }

    private function getClassesWithin($composerPath, $onCheck, $folder)
    {
        $results = [];
        foreach (FilePath::getAllPhpFiles($composerPath) as $classFilePath) {
            $absFilePath = $classFilePath->getRealPath();

            if ($folder && ! strpos($absFilePath, $folder)) {
                continue;
            }

            // Exclude blade files
            if (substr_count($classFilePath->getFilename(), '.') === 2) {
                continue;
            }

            [$currentNamespace, $class, $parent] = $this->readClass($absFilePath);

            // Skip if there is no class/trait/interface definition found.
            // For example a route file or a config file.
            if (! $class || $parent === 'Migration') {
                continue;
            }

            $onCheck && $onCheck($classFilePath->getRelativePathname());

            $results[] = [
                'currentNamespace' => $currentNamespace,
                'absFilePath' => $absFilePath,
                'class' => $class,
            ];
        }

        return $results;
    }

    private function readClass($absFilePath): array
    {
        $buffer = self::$buffer;
        do {
            [
                $currentNamespace,
                $class,
                $type,
                $parent,
            ] = GetClassProperties::fromFilePath($absFilePath, $buffer);
            $buffer = $buffer + 1000;
        } while ($currentNamespace && ! $class && $buffer < 6000);

        return [$currentNamespace, $class, $parent];
    }
}
