<?php

namespace Imanghafoori\LaravelMicroscope\Checks;

use Illuminate\Support\Str;
use Imanghafoori\LaravelMicroscope\ErrorReporters\ErrorPrinter;
use Imanghafoori\LaravelMicroscope\FileSystem\FileSystem;
use Imanghafoori\SearchReplace\PatternParser;
use Imanghafoori\SearchReplace\TokenCompare;
use Imanghafoori\TokenAnalyzer\Refactor;
use Imanghafoori\SearchReplace\Stringify;

class PatternRefactorings
{
    public static function check($tokens, $absFilePath, $classFilePath, $psr4Path, $psr4Namespace, $patterns)
    {
        foreach ($patterns[0] as $pattern) {
            if (isset($pattern['file']) && ! Str::endsWith($absFilePath, $pattern['file'])) {
                continue;
            }

            if (isset($pattern['directory']) && ! Str::startsWith($absFilePath, $pattern['directory'])) {
                continue;
            }

            $i = 0;
            start:
            $matchedValues = TokenCompare::getMatches($pattern['search'], $tokens, $pattern['predicate'], $pattern['mutator'], $i);

            if (! $matchedValues) {
                continue;
            }

            foreach ($matchedValues as $matchedValue) {
                $postReplaces = $pattern['post_replace'] ?? [];
                $avoid = $pattern['avoid_result_in'] ?? [];
                [$newTokens, $lineNum,] = PatternParser::applyMatch($pattern['replace'], $matchedValue, $tokens, $avoid, $postReplaces);

                if ($lineNum === null) {
                    continue;
                }

                $to = PatternParser::applyWithPostReplacements($pattern['replace'], $matchedValue['values'], $pattern['post_replace'] ?? []);
                $tokens = self::save($matchedValue, $tokens, $to, $lineNum, $absFilePath, $newTokens, $pattern['post_replace']);

                $i = self::continueFrom($to, $matchedValue);
                $tokens = token_get_all(Stringify::fromTokens($tokens));
                goto start;
            }
        }
    }

    private static function printLinks($lineNum, $absFilePath, $startingCode, $endResult)
    {
        $printer = app(ErrorPrinter::class);
        // Print Replacement Links
        $printer->print('Replacing:
<fg=yellow>'.Str::limit($startingCode, 150).'</>', '', 0);
        $printer->print('With:
<fg=yellow>'.Str::limit($endResult, 150).'</>', '', 0);

        $printer->print('<fg=red>Replacement will occur at:</>', '', 0);

        $lineNum && $printer->printLink($absFilePath, $lineNum, 0);
    }

    private static function askToRefactor($absFilePath)
    {
        $text = 'Do you want to replace '.basename($absFilePath).' with new version of it?';

        return app('current.command')->getOutput()->confirm($text, true);
    }

    private static function save($matchedValue, $tokens, $to, $lineNum, $absFilePath, $newTokens)
    {
        $from = TokenCompare::getPortion($matchedValue['start'] + 1, $matchedValue['end'] + 1, $tokens);
        self::printLinks($lineNum, $absFilePath, $from, $to);

        if (self::askToRefactor($absFilePath)) {
            FileSystem::$fileSystem::file_put_contents($absFilePath, Refactor::toString($newTokens));
            $tokens = $newTokens;
        }

        return $tokens;
    }

    private static function continueFrom(string $to, $matchedValue)
    {
        $diff = count(token_get_all('<?php '.$to)) - ($matchedValue['end'] - $matchedValue['start']) - 1;

        return $matchedValue['end'] + $diff;
    }
}
