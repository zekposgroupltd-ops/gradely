<?php
declare(strict_types=1);

namespace App\Services;

final class Passwords
{
    /** Random password without look-alike characters (no 0/O, 1/l/I). */
    public static function generate(int $length = 12): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
        $all = implode('', $sets);
        $out = [];
        foreach ($sets as $set) {
            $out[] = $set[random_int(0, strlen($set) - 1)];
        }
        while (count($out) < $length) {
            $out[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($out) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
        }
        return implode('', $out);
    }
}
