<div style="display:grid;grid-template-columns:1fr 1fr;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:32px;height:18px;border-radius:999px;background:{{ $oldOn ? '#6366f1' : '#475569' }};position:relative;flex-shrink:0;">
                <div style="position:absolute;top:2px;{{ $oldOn ? 'right:2px' : 'left:2px' }};width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);"></div>
            </div>
            <span style="font-size:.875rem;color:inherit;">
                {{ $oldOn ? __('filament-resource-lock::resource-lock.audit.diff.yes') : __('filament-resource-lock::resource-lock.audit.diff.no') }}
            </span>
        </div>
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:32px;height:18px;border-radius:999px;background:{{ $newOn ? '#6366f1' : '#475569' }};position:relative;flex-shrink:0;">
                <div style="position:absolute;top:2px;{{ $newOn ? 'right:2px' : 'left:2px' }};width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);"></div>
            </div>
            <span style="font-size:.875rem;color:inherit;">
                {{ $newOn ? __('filament-resource-lock::resource-lock.audit.diff.yes') : __('filament-resource-lock::resource-lock.audit.diff.no') }}
            </span>
        </div>
    </div>
</div>
