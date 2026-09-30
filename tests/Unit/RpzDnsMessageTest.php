<?php

namespace Tests\Unit;

use App\Services\RpzDnsMessage;
use PHPUnit\Framework\TestCase;

class RpzDnsMessageTest extends TestCase
{
    public function test_rejects_malformed_and_oversized_names_without_looping(): void
    {
        $header = pack('nnnnnn', 1, 0, 1, 0, 0, 0);
        $tail = pack('nn', 6, 1);
        foreach (["\xc0\x0c", "\xc0\x0e", "\xc0\x00", "\xc0", "\x40".str_repeat('a', 64)."\0", str_repeat("\x3f".str_repeat('a', 63), 4)."\0", "\x09abc", "\x03abc\0"] as $wire) {
            // The final entry has a valid name but a truncated question.
            $packet = $header.$wire.($wire === "\x03abc\0" ? "\0" : $tail);
            $this->assertNull(RpzDnsMessage::question($packet));
        }
    }

    public function test_rejects_multiple_questions(): void
    {
        $packet = pack('nnnnnn', 1, 0, 2, 0, 0, 0).RpzDnsMessage::encodeName('rpz.example').pack('nn', 6, 1);
        $this->assertNull(RpzDnsMessage::question($packet));
    }

    public function test_invalid_output_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RpzDnsMessage::encodeName(str_repeat('x', 64).'.example');
    }
}
