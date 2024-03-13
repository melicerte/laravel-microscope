<?php

namespace Imanghafoori\LaravelMicroscope\Commands;

use Illuminate\Console\Command;
use Imanghafoori\LaravelMicroscope\Checks\CheckEarlyReturn;
use Imanghafoori\LaravelMicroscope\ErrorReporters\ErrorPrinter;
use Imanghafoori\LaravelMicroscope\Features\CheckImports\Reporters\Psr4Report;
use Imanghafoori\LaravelMicroscope\ForPsr4LoadedClasses;

class CheckEndIf extends Command
{
    protected $signature = 'check:endif {--f|file=} {--d|folder=} {--t|test : backup the changed files}';

    protected $description = 'replaces ruby like syntax of php (endif) with curly brackets.';

    public function handle(ErrorPrinter $errorPrinter)
    {
        if (! $this->startWarning()) {
            return null;
        }

        $fileName = ltrim($this->option('file'), '=');
        $folder = ltrim($this->option('folder'), '=');

        $errorPrinter->printer = $this->output;

        $psr4Stats = ForPsr4LoadedClasses::check(
            [CheckEarlyReturn::class],
            $this->option('test'),
            $fileName,
            $folder
        );

        $this->getOutput()->writeln(implode(PHP_EOL, [
            Psr4Report::printAutoload($psr4Stats, []),
        ]));

        return ErrorPrinter::singleton()->hasErrors() ? 1 : 0;
    }

    private function startWarning()
    {
        $this->info('Checking for endif\'s...');
        $this->warn('This command is going to make changes to your files!');

        return $this->output->confirm('Do you have committed everything in git?');
    }
}
