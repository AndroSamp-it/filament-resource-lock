<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Services\ResourceLockUpdateDispatcher;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Androsamp\FilamentResourceLock\Transports\BroadcastResourceLockUpdateTransport;
use Androsamp\FilamentResourceLock\Transports\HeartbeatResourceLockUpdateTransport;
use InvalidArgumentException;

class ResourceLockUpdateDispatcherTest extends TestCase
{
    public function test_dispatches_without_error_in_heartbeat_mode(): void
    {
        config(['filament-resource-lock.update_driver' => 'heartbeat']);

        $dispatcher = new ResourceLockUpdateDispatcher();

        // HeartbeatTransport is a no-op; dispatch should complete silently.
        $dispatcher->dispatch(new TestModel(), 'test_event', ['foo' => 'bar']);

        $this->expectNotToPerformAssertions();
    }

    public function test_throws_for_unknown_driver(): void
    {
        config(['filament-resource-lock.update_driver' => 'unknown_driver']);

        $dispatcher = new ResourceLockUpdateDispatcher();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown_driver/');

        $dispatcher->dispatch(new TestModel(), 'test_event');
    }

    public function test_heartbeat_transport_is_no_op(): void
    {
        $transport = app(HeartbeatResourceLockUpdateTransport::class);

        // Should do nothing and not throw.
        $transport->send(new TestModel(), 'test_event');

        $this->expectNotToPerformAssertions();
    }

    public function test_broadcast_transport_is_resolvable(): void
    {
        $transport = app(BroadcastResourceLockUpdateTransport::class);

        $this->assertInstanceOf(BroadcastResourceLockUpdateTransport::class, $transport);
    }
}
