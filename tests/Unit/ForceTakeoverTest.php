<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Enums\ForceTakeover;
use Androsamp\FilamentResourceLock\Tests\TestCase;

class ForceTakeoverTest extends TestCase
{
    public function test_none_has_value_zero(): void
    {
        $this->assertSame(0, ForceTakeover::None->value);
    }

    public function test_save_has_value_one(): void
    {
        $this->assertSame(1, ForceTakeover::Save->value);
    }

    public function test_no_save_has_value_two(): void
    {
        $this->assertSame(2, ForceTakeover::NoSave->value);
    }

    public function test_try_from_returns_correct_case(): void
    {
        $this->assertSame(ForceTakeover::None, ForceTakeover::tryFrom(0));
        $this->assertSame(ForceTakeover::Save, ForceTakeover::tryFrom(1));
        $this->assertSame(ForceTakeover::NoSave, ForceTakeover::tryFrom(2));
    }

    public function test_try_from_returns_null_for_unknown_value(): void
    {
        $this->assertNull(ForceTakeover::tryFrom(99));
    }
}
