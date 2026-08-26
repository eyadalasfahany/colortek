<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_one_plus_one_equals_two(): void
    {
        $sum = 1 + 1;

        $this->assertSame(2, $sum);
    }
}
