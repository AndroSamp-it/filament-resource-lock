<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Support\ResourceLockBroadcastChannel;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceLockBroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_name_has_correct_format(): void
    {
        $record = TestModel::create(['name' => 'Test']);

        $channel = ResourceLockBroadcastChannel::forRecord($record);

        $prefix = config('filament-resource-lock.transports.broadcast.channel_prefix', 'filament-resource-lock');
        $hash = sha1(TestModel::class);

        $this->assertEquals("{$prefix}.{$hash}.{$record->getKey()}", $channel);
    }

    public function test_channel_name_uses_custom_prefix(): void
    {
        config(['filament-resource-lock.transports.broadcast.channel_prefix' => 'my-app-locks']);
        $record = TestModel::create(['name' => 'Test']);

        $channel = ResourceLockBroadcastChannel::forRecord($record);

        $this->assertStringStartsWith('my-app-locks.', $channel);
    }

    public function test_different_models_produce_different_channels(): void
    {
        $a = TestModel::create(['name' => 'A']);
        $b = TestModel::create(['name' => 'B']);

        $this->assertNotEquals(
            ResourceLockBroadcastChannel::forRecord($a),
            ResourceLockBroadcastChannel::forRecord($b),
        );
    }
}
