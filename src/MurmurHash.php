<?php

namespace Featurevisor;

class MurmurHash
{
    public static function v3(string $key, int $seed = 1): int
    {
        $key = mb_convert_encoding($key, 'UTF-8', 'UTF-8');
        $keyLength = strlen($key);

        $remainder = $keyLength & 3; // keyLength % 4
        $bytes = $keyLength - $remainder;
        $h1 = $seed;
        $c1 = 0xcc9e2d51;
        $c2 = 0x1b873593;
        $i = 0;

        while ($i < $bytes) {
            $k1 = (ord($key[$i]) & 0xff) |
                  ((ord($key[++$i]) & 0xff) << 8) |
                  ((ord($key[++$i]) & 0xff) << 16) |
                  ((ord($key[++$i]) & 0xff) << 24);
            ++$i;

            $k1 = (($k1 & 0xffff) * $c1 + (((($k1 >> 16) * $c1) & 0xffff) << 16)) & 0xffffffff;
            $k1 = ($k1 << 15) | ($k1 >> 17);
            $k1 = (($k1 & 0xffff) * $c2 + (((($k1 >> 16) * $c2) & 0xffff) << 16)) & 0xffffffff;

            $h1 ^= $k1;
            $h1 = ($h1 << 13) | ($h1 >> 19);
            $h1b = (($h1 & 0xffff) * 5 + (((($h1 >> 16) * 5) & 0xffff) << 16)) & 0xffffffff;
            $h1 = ($h1b & 0xffff) + 0x6b64 + (((($h1b >> 16) + 0xe654) & 0xffff) << 16);
        }

        $k1 = 0;

        switch ($remainder) {
            case 3:
                $k1 ^= (ord($key[$i + 2]) & 0xff) << 16;
            case 2:
                $k1 ^= (ord($key[$i + 1]) & 0xff) << 8;
            case 1:
                $k1 ^= ord($key[$i]) & 0xff;

                $k1 = (($k1 & 0xffff) * $c1 + (((($k1 >> 16) * $c1) & 0xffff) << 16)) & 0xffffffff;
                $k1 = ($k1 << 15) | ($k1 >> 17);
                $k1 = (($k1 & 0xffff) * $c2 + (((($k1 >> 16) * $c2) & 0xffff) << 16)) & 0xffffffff;
                $h1 ^= $k1;
        }

        $h1 ^= $keyLength;

        $h1 ^= $h1 >> 16;
        $h1 = (($h1 & 0xffff) * 0x85ebca6b + (((($h1 >> 16) * 0x85ebca6b) & 0xffff) << 16)) & 0xffffffff;
        $h1 ^= $h1 >> 13;
        $h1 = (($h1 & 0xffff) * 0xc2b2ae35 + (((($h1 >> 16) * 0xc2b2ae35) & 0xffff) << 16)) & 0xffffffff;
        $h1 ^= $h1 >> 16;

        return $h1 >> 0;
    }
}
