@once
    @vite('resources/js/app.js')
@endonce

@php
    $driver = $resourceLockUpdateDriver ?? config('filament-resource-lock.update_driver', 'heartbeat');
    $ttlSec = (int) config('filament-resource-lock.ttl_seconds', 20);
    $renewSec = config('filament-resource-lock.transports.broadcast.renew_interval_seconds');
    $renewSec = $renewSec !== null && $renewSec !== '' ? (int) $renewSec : max(5, (int) floor($ttlSec * 0.4));
    $renewMs = max(3000, $renewSec * 1000);
    $graceMs = ((int) config('filament-resource-lock.release_grace_seconds', 3)) * 1000 + 500;
    $heartbeatMs = (int)(($resourceLockHeartbeatSeconds ?? config('filament-resource-lock.transports.heartbeat.heartbeat_seconds', 10)) * 1000);
@endphp

{{--
    Heartbeat mode: use Alpine setInterval instead of wire:poll.
    wire:poll is unreliable in Filament SPA mode because the observer is rendered
    inside the persistent layout component. After a SPA navigation poll may stop
    firing or dispatch to the wrong component. Livewire.dispatch() is a global
    dispatch that reaches any component with a matching #[On] listener.
--}}
@if ($driver === 'heartbeat')
    <div
        x-data="{ heartbeatTimer: null }"
        x-init="heartbeatTimer = setInterval(() => Livewire.dispatch('resourceLock::resourceLockHeartbeat'), {{ $heartbeatMs }})"
        x-destroy="clearInterval(heartbeatTimer)"
    ></div>
@endif

@if ($driver === 'broadcast' && !empty($resourceLockBroadcastChannel))
    <div x-data="{
        channelName: '{{ $resourceLockBroadcastChannel }}',
        echoBootTimer: null,
        renewTimer: null,
        releaseHeartbeatTimer: null,
    }" x-init="
        const eventName = '.{{ config('filament-resource-lock.transports.broadcast.event', 'resource-lock.updated') }}';
        const currentSessionId = '{{ $resourceLockSessionId ?? '' }}';
        const renewIntervalMs = {{ $renewMs }};
        const immediateEvents = [
            'ask_to_unblock',
            'ask_to_unblock_declined',
            'ask_to_unblock_accepted',
            'save_and_unlock',
            'force_takeover_requested',
        ];

        const startLockRenewViaEcho = () => {
            clearInterval(renewTimer);
            renewTimer = setInterval(() => {
                Livewire.dispatch('resourceLock::resourceLockHeartbeat');
            }, renewIntervalMs);
        };

        const subscribe = () => {
            if (! window.Echo?.private) {
                return false;
            }

            window.Echo.private(channelName)
                .subscribed(() => {
                    startLockRenewViaEcho();
                })
                .listen(eventName, (data) => {
                if (! data?.event) {
                    return;
                }

                const skipOwnOrigin =
                    data?.payload?.origin_session_id &&
                    data.payload.origin_session_id === currentSessionId &&
                    !(
                        data.event === 'save_and_unlock' &&
                        data.payload?.evict_notify_session_id &&
                        data.payload.evict_notify_session_id === currentSessionId
                    );

                if (skipOwnOrigin) {
                    return;
                }

                if (data.event === 'lock_released') {
                    // Delay the heartbeat by the grace period to give the owner time to
                    // reload and reclaim the lock before waiting sessions try to acquire it.
                    clearTimeout(releaseHeartbeatTimer);
                    releaseHeartbeatTimer = setTimeout(() => {
                        Livewire.dispatch('resourceLock::resourceLockHeartbeat');
                    }, {{ $graceMs }});

                    return;
                }

                if (! immediateEvents.includes(data.event)) {
                    return;
                }

                Livewire.dispatch('resourceLock::resourceLockHeartbeat');
            });

            return true;
        };

        if (! subscribe()) {
            let attempts = 0;
            echoBootTimer = setInterval(() => {
                attempts++;

                if (subscribe() || attempts >= 10) {
                    if (attempts >= 10 && renewTimer === null) {
                        startLockRenewViaEcho();
                    }
                    clearInterval(echoBootTimer);
                    echoBootTimer = null;
                }
            }, 500);
        }
    "
        x-destroy="
            clearInterval(echoBootTimer);
            clearTimeout(releaseHeartbeatTimer);
            clearInterval(renewTimer);
            if (window.Echo?.leave) {
                window.Echo.leave(channelName);
            }
        ">
    </div>
@endif

{{--
    Broadcast mode: release the lock when leaving the page.
    In regular mode beforeunload / pagehide fire. In Filament SPA mode (wire:navigate)
    those events do NOT fire, so x-destroy explicitly calls release() when the Alpine
    component is destroyed (which includes SPA navigation away from the page).
    Calling release() twice (x-destroy + beforeunload) is safe: the backend is idempotent.
--}}
@if ($driver === 'broadcast' && !empty($resourceLockReleaseUrl))
    <div x-data="{ releaseUrl: '{{ $resourceLockReleaseUrl }}' }" x-init="
        const boundRelease = () => {
            fetch(releaseUrl, {
                method: 'GET',
                keepalive: true,
                credentials: 'same-origin',
                // Prevents Laravel's StartSession middleware from overwriting
                // session('_previous.url') with the release URL, which would
                // break Filament's Cancel button redirect after a page reload.
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(() => {});
        };
        $el._frlBoundRelease = boundRelease;
        window.addEventListener('beforeunload', boundRelease);
        window.addEventListener('pagehide', boundRelease);
    " x-destroy="
        window.removeEventListener('beforeunload', $el._frlBoundRelease);
        window.removeEventListener('pagehide', $el._frlBoundRelease);
        $el._frlBoundRelease();
    ">
    </div>
@endif

{{--
    Lock status update listener.
    x-destroy cleans up the window listener to prevent accumulation during SPA navigation.
--}}
<div x-data="{}" x-init="
    const updateLockStatusHandler = (event) => {
        const detail = event?.detail;
        if (! detail) {
            return;
        }
        const { yourSessionId, lockedSessionId } = detail;

        if (yourSessionId === lockedSessionId) {
            $dispatch('resourceLock::stopPolling');
            $dispatch('close-modal', { id: 'resourceLockedNotice' });
            $dispatch('close-modal', { id: 'resourceUnlockWait' });
            $dispatch('close-modal', { id: 'resourceUnlockWaitNotify' });
        }
    };
    $el._frlUpdateLockStatusHandler = updateLockStatusHandler;
    window.addEventListener('resourceLock::updateLockStatus', updateLockStatusHandler);
" x-destroy="
    window.removeEventListener('resourceLock::updateLockStatus', $el._frlUpdateLockStatusHandler);
">
</div>

{{--
    Notification closed listener.
    Livewire.dispatch() is used instead of $wire.dispatch() because in SPA mode
    $wire points to the layout component, not the page component that holds the
    #[On] listener. The global dispatch correctly reaches the right component.
--}}
<div x-data="{}" x-init="
    const notificationClosedHandler = (event) => {
        Livewire.dispatch('resourceLock::declineAskToUnblock', {
            notification_id: event?.detail?.id ?? null,
        });
    };
    $el._frlNotificationClosedHandler = notificationClosedHandler;
    window.addEventListener('notificationClosed', notificationClosedHandler);
" x-destroy="
    window.removeEventListener('notificationClosed', $el._frlNotificationClosedHandler);
">
</div>

{{-- Heartbeat mode: fast polling while waiting for the lock to be released --}}
@if ($driver === 'heartbeat')
    <div x-data="{ pollInterval: null }" x-init="
        const startFastPollingHandler = () => {
            clearInterval(pollInterval);
            pollInterval = setInterval(() => {
                Livewire.dispatch('resourceLock::resourceLockHeartbeat');
            }, 2000);
        };
        const stopPollingHandler = () => {
            clearInterval(pollInterval);
            pollInterval = null;
        };
        $el._frlStartFastPollingHandler = startFastPollingHandler;
        $el._frlStopPollingHandler = stopPollingHandler;
        window.addEventListener('resourceLock::startFastPolling', startFastPollingHandler);
        window.addEventListener('resourceLock::stopPolling', stopPollingHandler);
    " x-destroy="
        clearInterval(pollInterval);
        window.removeEventListener('resourceLock::startFastPolling', $el._frlStartFastPollingHandler);
        window.removeEventListener('resourceLock::stopPolling', $el._frlStopPollingHandler);
    ">
    </div>
@endif

<x-filament::modal id="resourceLockedNotice" icon="heroicon-o-lock-closed" icon-color="danger" :closeButton="false"
    :closeByClickingAway="false" width="2xl">
    <x-slot name="heading">
        {{ __('filament-resource-lock::resource-lock.blocked_resource_notice_modal.heading') }}
    </x-slot>

    <x-slot name="description">
        <div x-data="{ description: '' }"
            @open-modal.window="if ($event.detail.id === 'resourceLockedNotice') description = $event.detail.description"
            x-text="description">
        </div>
    </x-slot>

    <x-slot name="footerActions">
        {{-- Save and unlock --}}
        @if (is_null(config('filament-resource-lock.permission.save_and_unlock.permission')) || auth()->user()?->can(config('filament-resource-lock.permission.save_and_unlock.permission')))
        <x-filament::button color="primary"
            x-on:click="
                $dispatch('resourceLock::saveAndUnlock');
                $dispatch('resourceLock::startFastPolling');
                $dispatch('close-modal', { id: 'resourceLockedNotice' });
                $dispatch('open-modal', { id: 'resourceUnlockWait' });
            ">
            {{ __('filament-resource-lock::resource-lock.blocked_resource_notice_modal.save_and_unlock') }}
        </x-filament::button>
        @endif

        @if (is_null(config('filament-resource-lock.permission.ask_to_unblock.permission')) || auth()->user()?->can(config('filament-resource-lock.permission.ask_to_unblock.permission')))
        <x-filament::button color="primary"
            x-on:click="
                $dispatch('resourceLock::askToUnblock');
                $dispatch('resourceLock::startFastPolling');
                $dispatch('close-modal', { id: 'resourceLockedNotice' });
                $dispatch('close-modal', { id: 'resourceUnlockWait' });
                $dispatch('open-modal', { id: 'resourceUnlockWaitNotify' });
            ">
            {{ __('filament-resource-lock::resource-lock.blocked_resource_notice_modal.ask_to_unblock') }}
        </x-filament::button>
        @endif
        {{-- Back to list --}}
        <x-filament::button color="gray" x-on:click="$wire.dispatch('resourceLock::backToList')">
            {{ __('filament-resource-lock::resource-lock.blocked_resource_notice_modal.back') }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>

<x-filament::modal id="resourceUnlockWait" icon="heroicon-o-lock-closed" icon-color="danger" :closeButton="false"
    :closeByClickingAway="false" width="2xl">
    <x-slot name="heading">
        {{ __('filament-resource-lock::resource-lock.waiting_for_resource_unlock_modal.heading') }}
    </x-slot>

    <x-slot name="description">
        {{ __('filament-resource-lock::resource-lock.waiting_for_resource_unlock_modal.description') }}
    </x-slot>

    <x-slot name="footerActions">

    </x-slot>
</x-filament::modal>

<x-filament::modal id="resourceUnlockWaitNotify" icon="heroicon-o-clock" icon-color="info" :closeButton="false"
    :closeByClickingAway="false" width="2xl">
    <x-slot name="heading">
        {{ __('filament-resource-lock::resource-lock.waiting_for_resource_unlock_notify_modal.heading') }}
    </x-slot>

    <x-slot name="description">
        {{ __('filament-resource-lock::resource-lock.waiting_for_resource_unlock_notify_modal.description') }}
    </x-slot>

    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="$wire.dispatch('resourceLock::backToList')">
            {{ __('filament-resource-lock::resource-lock.blocked_resource_notice_modal.back') }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>
