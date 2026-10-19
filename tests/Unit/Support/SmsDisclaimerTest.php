<?php

namespace Tests\Unit\Support;

use App\Support\SmsDisclaimer;
use PHPUnit\Framework\TestCase;

class SmsDisclaimerTest extends TestCase
{
    public function test_disclaimer_is_appended_once(): void
    {
        $message = SmsDisclaimer::append('Pickup is ready.');

        $this->assertSame("Pickup is ready.\n\nDo not reply to this message.", $message);
        $this->assertSame($message, SmsDisclaimer::append($message));
    }
}
