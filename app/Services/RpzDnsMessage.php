<?php

namespace App\Services;

/** Minimal DNS wire codec shared by the SOA listener and NOTIFY transport. */
class RpzDnsMessage
{
    public static function encodeName(string $name): string
    {
        $name = rtrim($name, '.');
        $wire = '';
        foreach ($name === '' ? [] : explode('.', $name) as $label) {
            if ($label === '' || strlen($label) > 63) {
                throw new \InvalidArgumentException('Invalid DNS label');
            }
            $wire .= chr(strlen($label)).$label;
        }
        if (strlen($wire) + 1 > 255) {
            throw new \InvalidArgumentException('DNS name exceeds 255 bytes');
        }

        return $wire."\0";
    }

    /** @return array{id:int,flags:int,qname:string,qtype:int,qclass:int}|null */
    public static function question(string $packet): ?array
    {
        if (strlen($packet) < 12) {
            return null;
        }
        $header = unpack('nid/nflags/nqd/nan/nns/nar', substr($packet, 0, 12));
        if ($header['qd'] !== 1) {
            return null;
        }
        $offset = 12;
        $name = self::decodeName($packet, $offset);
        if ($name === null || strlen($packet) < $offset + 4) {
            return null;
        }
        $question = unpack('nqtype/nqclass', substr($packet, $offset, 4));

        return ['id' => $header['id'], 'flags' => $header['flags'], 'qname' => $name] + $question;
    }

    private static function decodeName(string $packet, int &$offset): ?string
    {
        $labels = [];
        $end = null;
        $seen = [];
        $wireLength = 1;
        while ($offset < strlen($packet)) {
            if (isset($seen[$offset])) {
                return null;
            }
            $seen[$offset] = true;
            $length = ord($packet[$offset]);
            if ($length === 0) {
                $offset = $end ?? $offset + 1;

                return implode('.', $labels);
            }
            if (($length & 0xC0) === 0xC0) {
                if ($offset + 1 >= strlen($packet)) {
                    return null;
                }
                $pointer = unpack('n', substr($packet, $offset, 2))[1] & 0x3FFF;
                if ($pointer < 12 || $pointer >= $offset) {
                    return null;
                }
                $end ??= $offset + 2;
                $offset = $pointer;

                continue;
            }
            if ($length > 63 || $offset + 1 + $length > strlen($packet)) {
                return null;
            }
            $wireLength += $length + 1;
            if ($wireLength > 255) {
                return null;
            }
            $labels[] = substr($packet, $offset + 1, $length);
            $offset += $length + 1;
        }

        return null;
    }
}
