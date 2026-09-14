<?php

namespace Tests\Unit;

use App\Services\WebhookResponseLimit;
use PHPUnit\Framework\TestCase;

class WebhookResponseLimitTest extends TestCase
{
    public function test_chunked_body_aborts_at_the_limit_without_retaining_body_data(): void
    {
        $limit = new WebhookResponseLimit;
        $this->assertSame(32768, $limit->body(str_repeat('x', 32768)));
        $this->assertSame(32768, $limit->body(str_repeat('x', 32768)));
        $this->assertSame(0, $limit->body('one byte beyond the limit'));
        $this->assertTrue($limit->exceeded);
    }

    public function test_oversized_headers_abort_independently_of_the_body(): void
    {
        $limit = new WebhookResponseLimit;
        $this->assertSame(16384, $limit->headers(str_repeat('x', 16384)));
        $this->assertSame(0, $limit->headers('X-Extra: value'));
        $this->assertTrue($limit->exceeded);
    }
}
