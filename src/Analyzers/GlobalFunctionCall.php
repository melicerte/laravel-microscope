<?php

namespace Imanghafoori\LaravelMicroscope\Analyzers;

class GlobalFunctionCall
{
    public static function detect($funcName, &$tokens, $i)
    {
        $token = $tokens[$i];

        if ($token[0] != '(') {
            return [null, null];
        }

        [$nextToken, $c] = self::getNextToken($tokens, $i);
        [$nextToken2] = self::getNextToken($tokens, $c);

        $token = self::getPrevToken($tokens, $i);

        $prev1 = $tokens[$i - 1][0];
        $prev2 = $tokens[$i - 2][0];
        if ($token[0] != T_STRING || $token[1] != $funcName || ! self::isAfterWhiteSpace($prev1) || self::isAfterOp($prev1, $prev2, [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NEW, T_FUNCTION])) {
            return [null, null];
        }

        $param1 = null;

        // it should be a hard-coded string which is not concatinated like this: 'hi'. $there
        if (self::isSolidString($nextToken, $nextToken2)) {
            $param1 = $nextToken[1];
        }

        return [$param1, $token];
    }

    protected static function getNextToken($tokens, $i)
    {
        $i++;
        $nextToken = $tokens[$i];
        if ($nextToken[0] == T_WHITESPACE) {
            $i++;
            $nextToken = $tokens[$i];
        }

        return [$nextToken, $i];
    }

    protected static function getPrevToken($tokens, &$i)
    {
        $i--;
        $token = $tokens[$i];
        if ($token[0] == T_WHITESPACE) {
            $i--;
            $token = $tokens[$i];
        }

        return $token;
    }

    protected static function isAfterWhiteSpace($prev1)
    {
        return $prev1 == T_WHITESPACE;
    }

    protected static function isAfterOp($prev1, $prev2, $operators)
    {
        if (in_array($prev1, $operators)) {
            return true;
        }

        if ($prev1 == T_WHITESPACE && in_array($prev2, $operators)) {
            return true;
        }

        return false;
    }

    private static function isSolidString($nextToken, $nextToken2)
    {
        return $nextToken[0] == T_CONSTANT_ENCAPSED_STRING && $nextToken2 !== '.';
    }
}
