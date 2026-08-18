<?php

namespace Androsamp\FilamentResourceLock\Http\Controllers;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Services\ResourceLockUpdateDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseLockController
{
    /**
     * Soft-releases the lock on page unload so that a reload of the same session
     * can instantly reclaim it, while other sessions wait out the grace period.
     *
     * Class-based route handlers are stored by class name in the route cache,
     * avoiding the cyclic object graph that closures/first-class callables
     * capturing the container would introduce (route:cache safety).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $lockableType = (string) $request->query('lockable_type', '');
        $lockableId = $request->query('lockable_id');
        $sessionId = (string) $request->query('session_id', '');
        $userId = $request->query('user_id');

        if (! $this->isValidLockableType($lockableType) || $lockableId === null || $sessionId === '') {
            return response()->json(['ok' => false], 422);
        }

        $record = new $lockableType;
        $record->setAttribute($record->getKeyName(), $lockableId);

        app(ResourceLockManager::class)->softRelease(
            record: $record,
            userId: $userId !== null ? (int) $userId : null,
            sessionId: $sessionId,
        );

        app(ResourceLockUpdateDispatcher::class)->dispatch(
            record: $record,
            event: 'lock_released',
            payload: [
                'origin_session_id' => $sessionId,
                'lockable_type' => $lockableType,
                'lockable_id' => $lockableId,
            ],
        );

        return response()->json(['ok' => true]);
    }

    private function isValidLockableType(string $type): bool
    {
        return $type !== ''
            && class_exists($type)
            && is_subclass_of($type, Model::class);
    }
}
