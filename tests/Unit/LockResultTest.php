<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Support\LockResult;
use Androsamp\FilamentResourceLock\Tests\TestCase;

class LockResultTest extends TestCase
{
    public function test_acquired_is_true_and_lock_is_set(): void
    {
        $result = new LockResult(true, null);

        $this->assertTrue($result->acquired);
        $this->assertNull($result->lock);
    }

    public function test_acquired_is_false_when_conflict(): void
    {
        $result = new LockResult(false, null);

        $this->assertFalse($result->acquired);
    }

    public function test_properties_are_readonly(): void
    {
        $result = new LockResult(true, null);

        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line
        $result->acquired = false;
    }
}
