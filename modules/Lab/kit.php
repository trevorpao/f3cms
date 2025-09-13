<?php

namespace F3CMS;

/**
 * kit lib
 */
class kLab extends Kit
{
    public static function getHashCost($timeTarget = 0.05)
    {
        $timeTarget = 0.05; // 50 milliseconds

        $cost = 8;
        do {
            ++$cost;
            $start = microtime(true);
            password_hash('test', PASSWORD_BCRYPT, ['cost' => $cost]);
            $end = microtime(true);
        } while (($end - $start) < $timeTarget);

        return $cost;
    }
}
