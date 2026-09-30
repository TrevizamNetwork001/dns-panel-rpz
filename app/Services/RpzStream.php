<?php

namespace App\Services;

class RpzStream
{
    /** fwrite may return a short count, especially on a full filesystem. */
    public static function writeAll($stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Failed to write complete RPZ artifact');
            }
            $offset += $written;
        }
    }
}
