<?php

namespace Androsamp\FilamentResourceLock\Concerns;

use Androsamp\FilamentResourceLock\Enums\ForceTakeover;
use Androsamp\FilamentResourceLock\Models\ResourceLock;
use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Services\ResourceLockUpdateDispatcher;
use Androsamp\FilamentResourceLock\Support\ResourceLockBroadcastChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

trait InteractsWithResourceLock
{
    public bool $resourceLockConflict = false;
    public bool $resourceSaveAndUnlock = false;
    public ?ResourceLock $resourceLockOwner = null;

    // Stored separately to survive Livewire serialization without loading the relation.
    public ?string $resourceLockOwnerName = null;

    public ?string $resourceLockSessionId = null;
    public ?string $resourceLockReleaseUrl = null;
    public array $resourceLockPendingUnlockNotifications = [];

    public function bootInteractsWithResourceLock(): void
    {
        FilamentView::registerRenderHook (
            PanelsRenderHook::PAGE_START,
            fn() => view ('filament-resource-lock::components.resource-lock-observer', [
                'resourceLockUpdateDriver' => $this->getResourceLockUpdateDriver (),
                'resourceLockHeartbeatSeconds' => $this->getResourceLockHeartbeatSeconds (),
                'resourceLockBroadcastChannel' => $this->getResourceLockBroadcastChannel (),
                'resourceLockSessionId' => $this->resourceLockSessionId,
                'resourceLockReleaseUrl' => $this->resourceLockReleaseUrl,
            ]),
        );
    }

    public function mountInteractsWithResourceLock(): void
    {
        if (! method_exists ($this, 'getRecord')) {
            return;
        }

        $this->resourceLockSessionId = session ()->getId ();
        $this->resourceLockReleaseUrl = $this->getResourceLockReleaseUrl ();

        $this->performHeartbeat (skipRenderWhenStale: false);
    }

    #[On('resourceLock::resourceLockHeartbeat')]
    public function resourceLockHeartbeat(): void
    {
        // Called via Livewire event dispatch (AJAX) — safe to skip re-render when
        // nothing requires a visual update, so open modals are not disrupted.
        $this->performHeartbeat (skipRenderWhenStale: true);
    }

    private function performHeartbeat(bool $skipRenderWhenStale): void
    {
        $record = $this->getLockableRecord ();

        if (! $record) {
            if ($skipRenderWhenStale) {
                $this->suppressFilamentPartialRender ();
            }
            return;
        }

        $wasConflict = $this->resourceLockConflict;

        // Detect force-takeover before onLockAcquired clears the flag from the DB.
        $forceTakeover = false;

        $result = $this->getResourceLockManager ()->acquireOrRefresh (
            record: $record,
            userId: $this->getResourceLockUserId (),
            sessionId: $this->resourceLockSessionId,
        );

        if ($result->acquired) {
            $forceTakeover = $this->resourceLockSessionId === ($result->lock?->force_takeover_session_id ?? '');
        }

        $hasPendingRequest = $result->lock
            ? $this->processPendingLockEvents ($result->lock, $record)
            : false;

        if ($result->acquired) {
            $this->onLockAcquired ($result->lock, $record);
        } else {
            $this->onLockConflict ($result->lock, $hasPendingRequest);
        }

        $salt = Str::random (40);
        $this->dispatch (
            'resourceLock::updateLockStatus',
            yourSessionId: hash ('sha256', session ()->getId () . $salt),
            lockedSessionId: hash ('sha256', ($result->lock?->session_id ?? '') . $salt),
        );

        if (! $skipRenderWhenStale) {
            return;
        }

        if (! $forceTakeover && $wasConflict === $this->resourceLockConflict) {
            // Nothing changed visually — skip both HTML and action-modal partial re-render.
            // Without calling skipPartialRender(), Filament's PartialsComponentHook would
            // detect the method call and re-render the "action-modals" partial, which morphs
            // the DOM and resets Alpine.js modal state (closing any open modals).
            $this->suppressFilamentPartialRender ();
        } else {
            // Conflict state changed or force-takeover — need a full re-render so the form
            // reflects the new disabled state. forceRender() bypasses partial-only rendering.
            $this->triggerFilamentForceRender ();
        }
    }

    private function suppressFilamentPartialRender(): void
    {
        app (PartialsComponentHook::class)->skipPartialRender ($this);
    }

    private function triggerFilamentForceRender(): void
    {
        app (PartialsComponentHook::class)->forceRender ($this);
    }

    public function unmountInteractsWithResourceLock(): void
    {
        $record = $this->getLockableRecord ();

        if (! $record) {
            return;
        }

        $this->getResourceLockManager ()->release (
            record: $record,
            userId: $this->getResourceLockUserId (),
            sessionId: $this->resourceLockSessionId,
        );
    }

    #[On('resourceLock::saveAndUnlock')]
    public function saveAndUnlock(?int $user_id = null, ?string $session_id = null): void
    {
        if (! is_null (config ('filament-resource-lock.permission.save_and_unlock.permission')) && ! auth ()->user ()?->can (config ('filament-resource-lock.permission.save_and_unlock.permission'))) {
            return;
        }

        $record = $this->getLockableRecord ();

        if (! $record) {
            return;
        }

        $this->removePendingUnlockNotification ($user_id, $session_id);

        $lock = $this->getResourceLockManager ()->find ($record);

        if (! $lock) {
            return;
        }

        if ($this->resourceLockSessionId === $lock->session_id) {
            $this->transferLockOwnership ($lock, $record, $user_id, $session_id);
        } else {
            $this->requestForceTakeover ($lock, $record, $user_id, $session_id);
        }
    }

    #[On('resourceLock::askToUnblock')]
    public function askToUnblock(): void
    {
        if (! is_null (config ('filament-resource-lock.permission.ask_to_unblock.permission')) && ! auth ()->user ()?->can (config ('filament-resource-lock.permission.ask_to_unblock.permission'))) {
            return;
        }

        $record = $this->getLockableRecord ();

        if (! $record) {
            return;
        }

        $event = [
            'event' => 'ask_to_unblock',
            'user_id' => $this->getResourceLockUserId (),
            'session_id' => $this->resourceLockSessionId,
            'timestamp' => now ()->toDateTimeString (),
        ];

        $this->getResourceLockManager ()->appendEvent ($record, $event);
        $this->dispatchResourceLockUpdate ('ask_to_unblock', [
            'session_id' => $this->resourceLockSessionId,
            'user_id' => $this->getResourceLockUserId (),
        ]);
    }

    #[On('resourceLock::declineAskToUnblock')]
    public function declineAskToUnblock(
        ?string $notification_id = null,
        ?int $user_id = null,
        ?string $session_id = null,
    ): void {
        $record = $this->getLockableRecord ();

        if (! $record) {
            return;
        }

        $pending = $notification_id
            ? ($this->resourceLockPendingUnlockNotifications[$notification_id] ?? null)
            : null;

        if ($notification_id) {
            unset ($this->resourceLockPendingUnlockNotifications[$notification_id]);
        }

        $targetUserId = $user_id ?? ($pending['user_id'] ?? null);
        $targetSessionId = $session_id ?? ($pending['session_id'] ?? null);

        if (! $targetSessionId || ! $this->getResourceLockManager ()->find ($record)) {
            return;
        }

        $event = [
            'event' => 'ask_to_unblock_declined',
            'target_user_id' => $targetUserId,
            'target_session_id' => $targetSessionId,
            'declined_by_user_id' => $this->getResourceLockUserId (),
            'timestamp' => now ()->toDateTimeString (),
        ];

        $this->getResourceLockManager ()->appendEvent ($record, $event);
        $this->getResourceLockManager ()->removeAskToUnblockForRequester ($record, $targetUserId, $targetSessionId);
        $this->dispatchResourceLockUpdate ('ask_to_unblock_declined', [
            'target_session_id' => $targetSessionId,
            'target_user_id' => $targetUserId,
            'declined_by_user_id' => $this->getResourceLockUserId (),
        ]);
    }

    #[On('resourceLock::backToList')]
    public function resourceLockRedirect(): void
    {
        $this->redirect ($this->getResourceLockRedirectUrl ());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm ($schema)
            ->disabled (fn(): bool => $this->getResourceLockSessionId () !== $this->resourceLockSessionId);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction ()
            ->disabled (fn(): bool => $this->getResourceLockSessionId () !== $this->resourceLockSessionId);
    }

    public function getResourceLockOwnerForceTakeover(): ForceTakeover
    {
        $record = $this->getLockableRecord ();

        if (! $record) {
            return ForceTakeover::None;
        }

        $lock = $this->getResourceLockManager ()->find ($record);

        return ForceTakeover::tryFrom ($lock?->force_takeover ?? 0) ?? ForceTakeover::None;
    }

    public function getResourceLockSessionId(): string|false
    {
        $record = $this->getLockableRecord ();

        if (! $record) {
            return false;
        }

        $lock = $this->getResourceLockManager ()->find ($record);

        return $lock?->session_id ?? false;
    }

    // -------------------------------------------------------------------------
    // Infrastructure
    // -------------------------------------------------------------------------

    protected function getLockableRecord(): ?Model
    {
        return method_exists ($this, 'getRecord') ? $this->getRecord () : null;
    }

    protected function getResourceLockManager(): ResourceLockManager
    {
        return app (ResourceLockManager::class);
    }

    protected function getResourceLockUserId(): ?int
    {
        return auth ()->id ();
    }

    protected function getResourceLockHeartbeatSeconds(): int
    {
        return (int) config ('filament-resource-lock.transports.heartbeat.heartbeat_seconds', 10);
    }

    protected function getResourceLockUpdateDriver(): string
    {
        return (string) config ('filament-resource-lock.update_driver', 'heartbeat');
    }

    protected function getResourceLockBroadcastChannel(): ?string
    {
        $record = $this->getLockableRecord ();

        return $record ? ResourceLockBroadcastChannel::forRecord ($record) : null;
    }

    protected function getResourceLockReleaseUrl(): ?string
    {
        $record = $this->getLockableRecord ();

        if (! $record || ! $this->resourceLockSessionId) {
            return null;
        }

        return URL::temporarySignedRoute (
            'filament-resource-lock.release',
            now ()->addHours (12),
            [
                'lockable_type' => $record::class,
                'lockable_id' => $record->getKey (),
                'session_id' => $this->resourceLockSessionId,
                'user_id' => $this->getResourceLockUserId (),
            ],
        );
    }

    protected function getResourceLockRedirectUrl(): string
    {
        return method_exists ($this, 'getResourceUrl')
            ? $this->getResourceUrl ()
            : url ()->previous ();
    }

    protected function dispatchResourceLockUpdate(string $event, array $payload = []): void
    {
        $record = $this->getLockableRecord ();

        if (! $record) {
            return;
        }

        $payload['origin_session_id'] = $this->resourceLockSessionId;

        app (ResourceLockUpdateDispatcher::class)->dispatch (
            record: $record,
            event: $event,
            payload: $payload,
        );
    }

    // -------------------------------------------------------------------------
    // Lock state handlers
    // -------------------------------------------------------------------------

    private function onLockAcquired(ResourceLock $lock, Model $record): void
    {
        // When the lock was force-transferred to this session, clear the takeover
        // markers and refresh the form so the new owner sees fresh data.
        if ($this->resourceLockSessionId === $lock->force_takeover_session_id) {
            $this->getResourceLockManager ()->update ($record, [
                'force_takeover_session_id' => null,
                'force_takeover_user_id' => null,
            ]);

            $this->record->refresh ();
            $this->form->fill ($this->record->attributesToArray ());
        }

        if (
            $this->getResourceLockOwnerForceTakeover () === ForceTakeover::Save
            && $this->resourceLockSessionId === $lock->session_id
        ) {
            $this->saveAndUnlock ();
        }

        $this->resourceLockConflict = false;
        $this->resourceLockOwner = null;
        $this->resourceLockOwnerName = null;
    }

    private function onLockConflict(?ResourceLock $lock, bool $hasPendingRequest): void
    {
        $wasConflict = $this->resourceLockConflict;

        $this->resourceLockConflict = true;
        $this->resourceLockOwner = $lock;
        // Reset cached name before recalculating so we always get a fresh value.
        $this->resourceLockOwnerName = null;
        $this->resourceLockOwnerName = $this->getResourceLockOwnerDisplayName ();

        if ($wasConflict) {
            return;
        }

        if ($hasPendingRequest) {
            $this->dispatch ('resourceLock::startFastPolling');
            $this->dispatch ('open-modal', id: 'resourceUnlockWaitNotify');
        } else {
            $this->dispatch (
                'open-modal',
                id: 'resourceLockedNotice',
                description: __ ('filament-resource-lock::resource-lock.blocked_resource_notice_modal.description', [
                    'user' => $this->resourceLockOwnerName,
                ]),
            );
        }
    }

    // -------------------------------------------------------------------------
    // Event processing
    // -------------------------------------------------------------------------

    /**
     * Processes queued lock events addressed to this session. Returns true when
     * there is an unresolved ask_to_unblock request from the current session.
     */
    private function processPendingLockEvents(ResourceLock $lock, Model $record): bool
    {
        $events = $this->decodeLockEvents ($lock->events);

        if (empty ($events)) {
            return false;
        }

        $hasPendingRequest = $this->hasPendingAskToUnblockForCurrentSession ($events);

        foreach ($events as $key => $event) {
            if (! $this->shouldHandleResourceLockEvent ($event, $lock->session_id)) {
                continue;
            }

            $this->handleLockEvent ($event, $record);

            // Keep ask_to_unblock in the queue: the lock owner must see it even
            // after a page reload, while the requester is still waiting.
            if ($event['event'] !== 'ask_to_unblock') {
                unset ($events[$key]);
            }
        }

        $this->getResourceLockManager ()->setEvents ($record, array_values ($events));

        return $hasPendingRequest;
    }

    private function handleLockEvent(array $event, Model $record): void
    {
        match ($event['event'] ?? '') {
            'ask_to_unblock' => $this->onAskToUnblockEvent ($event, $record),
            'ask_to_unblock_declined' => $this->onAskToUnblockDeclinedEvent ($event),
            'ask_to_unblock_accepted' => $this->onAskToUnblockAcceptedEvent ($event),
            'evicted_after_save_unlock' => $this->onEvictedAfterSaveUnlockEvent ($event),
            default => null,
        };
    }

    private function onAskToUnblockEvent(array $event, Model $record): void
    {
        $requesterUserId = isset ($event['user_id']) ? (int) $event['user_id'] : null;
        $requesterSession = (string) ($event['session_id'] ?? '');
        $notificationId = $this->getAskToUnblockNotificationId ($record, $requesterUserId, $requesterSession);

        $this->resourceLockPendingUnlockNotifications[$notificationId] = [
            'user_id' => $event['user_id'] ?? null,
            'session_id' => $event['session_id'] ?? null,
        ];

        Notification::make ()
            ->id ($notificationId)
            ->title (__ ('filament-resource-lock::resource-lock.notifications.ask_to_unblock.title'))
            ->body (__ ('filament-resource-lock::resource-lock.notifications.ask_to_unblock.body', [
                'user' => $this->resolveUserName ($requesterUserId),
            ]))
            ->warning ()
            ->persistent ()
            ->actions ([
                Action::make ('free_resource')
                    ->label (__ ('filament-resource-lock::resource-lock.notifications.ask_to_unblock.accept'))
                    ->color ('warning')
                    ->dispatch ('resourceLock::saveAndUnlock', [
                        'user_id' => $event['user_id'],
                        'session_id' => $event['session_id'],
                    ])
                    ->close ()
                    ->button (),
                Action::make ('decline')
                    ->label (__ ('filament-resource-lock::resource-lock.notifications.ask_to_unblock.decline'))
                    ->color ('danger')
                    ->dispatch ('resourceLock::declineAskToUnblock', [
                        'notification_id' => $notificationId,
                        'user_id' => $event['user_id'] ?? null,
                        'session_id' => $event['session_id'] ?? null,
                    ])
                    ->close ()
                    ->button (),
            ])
            ->send ();
    }

    private function onAskToUnblockDeclinedEvent(array $event): void
    {
        $declinedBy = $this->resolveUserName (
            isset ($event['declined_by_user_id']) ? (int) $event['declined_by_user_id'] : null,
        );

        $this->dispatch (
            'open-modal',
            id: 'resourceLockedNotice',
            description: __ ('filament-resource-lock::resource-lock.blocked_resource_notice_modal.description', [
                'user' => $this->resourceLockOwnerName ?? $this->getResourceLockOwnerDisplayName (),
            ]),
        );
        $this->dispatch ('resourceLock::stopPolling');
        $this->dispatch ('close-modal', id: 'resourceUnlockWait');
        $this->dispatch ('close-modal', id: 'resourceUnlockWaitNotify');

        Notification::make ()
            ->title (__ ('filament-resource-lock::resource-lock.notifications.unlock_declined.title'))
            ->body (__ ('filament-resource-lock::resource-lock.notifications.unlock_declined.body', [
                'user' => $declinedBy,
            ]))
            ->danger ()
            ->send ();
    }

    private function onAskToUnblockAcceptedEvent(array $event): void
    {
        $acceptedBy = $this->resolveUserName (
            isset ($event['accepted_by_user_id']) ? (int) $event['accepted_by_user_id'] : null,
        );

        Notification::make ()
            ->title (__ ('filament-resource-lock::resource-lock.notifications.unlock_accepted.title'))
            ->body (__ ('filament-resource-lock::resource-lock.notifications.unlock_accepted.body', [
                'user' => $acceptedBy,
            ]))
            ->success ()
            ->send ();
    }

    private function onEvictedAfterSaveUnlockEvent(array $event): void
    {
        $newOwner = $this->resolveUserName (
            isset ($event['new_owner_user_id']) ? (int) $event['new_owner_user_id'] : null,
        );

        Notification::make ()
            ->title (__ ('filament-resource-lock::resource-lock.evicted_after_save_unlock.title'))
            ->body (__ ('filament-resource-lock::resource-lock.evicted_after_save_unlock.body', [
                'user' => $newOwner,
            ]))
            ->warning ()
            ->send ();
    }

    // -------------------------------------------------------------------------
    // Save-and-unlock helpers
    // -------------------------------------------------------------------------

    /**
     * Current session owns the lock: save the form and transfer ownership to the
     * requester (or the force-takeover candidate if no explicit requester).
     */
    private function transferLockOwnership(
        ResourceLock $lock,
        Model $record,
        ?int $requesterUserId,
        ?string $requesterSessionId,
    ): void {
        $previousSessionId = (string) ($lock->session_id ?? '');
        $previousUserId = $lock->user_id;
        $events = $this->decodeLockEvents ($lock->events);

        if ($requesterUserId !== null && $requesterSessionId !== null) {
            $this->getResourceLockManager ()->removeAskToUnblockForRequester (
                $record,
                $requesterUserId,
                $requesterSessionId,
            );

            $lock = $this->getResourceLockManager ()->find ($record);

            if (! $lock) {
                return;
            }

            $events = $this->decodeLockEvents ($lock->events);
            $events[] = [
                'event' => 'ask_to_unblock_accepted',
                'target_user_id' => $requesterUserId,
                'target_session_id' => $requesterSessionId,
                'accepted_by_user_id' => $this->getResourceLockUserId (),
                'timestamp' => now ()->toDateTimeString (),
            ];
        }

        $newSessionId = $requesterSessionId ?? $lock->force_takeover_session_id;
        $newUserId = $requesterUserId ?? $lock->force_takeover_user_id;
        $sessionChanged = $newSessionId !== null
            && $previousSessionId !== ''
            && (string) $newSessionId !== $previousSessionId;

        if ($sessionChanged) {
            $events[] = [
                'event' => 'evicted_after_save_unlock',
                'target_session_id' => $previousSessionId,
                'target_user_id' => $previousUserId,
                'new_owner_user_id' => $newUserId,
                'timestamp' => now ()->toDateTimeString (),
            ];
        }

        $this->save ();

        $this->getResourceLockManager ()->update ($record, [
            'force_takeover' => ForceTakeover::None->value,
            'user_id' => $newUserId,
            'session_id' => $newSessionId,
            'events' => $events,
        ]);

        $this->resourceLockHeartbeat ();

        $this->dispatchResourceLockUpdate ('save_and_unlock', [
            'target_session_id' => $requesterSessionId,
            'target_user_id' => $requesterUserId,
            'accepted_by_user_id' => $this->getResourceLockUserId (),
            'evict_notify_session_id' => $sessionChanged ? $previousSessionId : null,
        ]);
    }

    /**
     * Current session does not own the lock: signal the owner to save and hand
     * over via the force_takeover mechanism.
     */
    private function requestForceTakeover(
        ResourceLock $lock,
        Model $record,
        ?int $userId,
        ?string $sessionId,
    ): void {
        $this->getResourceLockManager ()->update ($record, [
            'force_takeover' => ForceTakeover::Save->value,
            'force_takeover_user_id' => $userId ?? $this->getResourceLockUserId (),
            'force_takeover_session_id' => $sessionId ?? $this->resourceLockSessionId,
        ]);

        $this->dispatchResourceLockUpdate ('force_takeover_requested', [
            'target_session_id' => $sessionId ?? $this->resourceLockSessionId,
            'target_user_id' => $userId ?? $this->getResourceLockUserId (),
        ]);
    }

    private function removePendingUnlockNotification(?int $userId, ?string $sessionId): void
    {
        if ($userId === null || $sessionId === null) {
            return;
        }

        foreach ($this->resourceLockPendingUnlockNotifications as $notificationId => $notification) {
            if (
                ($notification['user_id'] ?? null) === $userId
                && ($notification['session_id'] ?? null) === $sessionId
            ) {
                unset ($this->resourceLockPendingUnlockNotifications[$notificationId]);
                break;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Display helpers
    // -------------------------------------------------------------------------

    protected function getResourceLockOwnerDisplayName(): string
    {
        if (! empty ($this->resourceLockOwnerName)) {
            return $this->resourceLockOwnerName;
        }

        // Redis mode: display name is stored directly in the payload.
        $fromPayload = $this->resourceLockOwner?->getAttribute ('user_display_name');
        if (! empty ($fromPayload)) {
            return (string) $fromPayload;
        }

        // Database mode: resolve via the user relation.
        $user = $this->resourceLockOwner?->user;
        $column = (string) config ('filament-resource-lock.user_display_column', 'name');

        if (! $user) {
            return __ ('filament-resource-lock::resource-lock.other_user');
        }

        return (string) ($user->{$column} ?? $user->getKey () ?? __ ('filament-resource-lock::resource-lock.other_user'));
    }

    protected function shouldHandleResourceLockEvent(array $event, ?string $lockSessionId): bool
    {
        if (! empty ($event['target_session_id'])) {
            return $event['target_session_id'] === $this->resourceLockSessionId;
        }

        // ask_to_unblock has no target_session_id; it goes to whoever currently owns the lock.
        if (($event['event'] ?? null) === 'ask_to_unblock') {
            return $this->resourceLockSessionId === $lockSessionId;
        }

        return false;
    }

    protected function hasPendingAskToUnblockForCurrentSession(array $events): bool
    {
        foreach ($events as $event) {
            if (($event['event'] ?? '') !== 'ask_to_unblock') {
                continue;
            }

            if ((string) ($event['session_id'] ?? '') === (string) $this->resourceLockSessionId) {
                return true;
            }
        }

        return false;
    }

    protected function getAskToUnblockNotificationId(Model $record, ?int $requesterUserId, string $requesterSessionId): string
    {
        $hash = hash (
            'sha256',
                $record::class . '|' . $record->getKey () . '|' . ($requesterUserId ?? '') . '|' . $requesterSessionId,
        );

        return 'resource-lock-ask-' . substr ($hash, 0, 40);
    }

    /**
     * Resolves a user's display name by their ID using the configured user model
     * and display column. Falls back to the "other user" translation if not found.
     */
    private function resolveUserName(?int $userId): string
    {
        if ($userId === null) {
            return __ ('filament-resource-lock::resource-lock.other_user');
        }

        $userModel = config ('filament-resource-lock.user_model', \App\Models\User::class);
        $column = (string) config ('filament-resource-lock.user_display_column', 'name');
        $user = $userModel::find ($userId);

        return $user
            ? (string) ($user->{$column} ?? ('ID: ' . $userId))
            : ('ID: ' . $userId);
    }

    private function decodeLockEvents(mixed $events): array
    {
        if (is_string ($events)) {
            return json_decode ($events, true) ?? [];
        }

        return is_array ($events) ? $events : [];
    }
}
