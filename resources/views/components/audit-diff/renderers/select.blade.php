<div style="display:grid;grid-template-columns:1fr 1fr;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:500;background:rgba(239,68,68,.15);color:#fca5a5;">
            <span style="width:6px;height:6px;border-radius:50%;background:#f87171;flex-shrink:0;"></span>
            {{ $oldSelect }}
        </span>
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:500;background:rgba(34,197,94,.15);color:#86efac;">
            <span style="width:6px;height:6px;border-radius:50%;background:#4ade80;flex-shrink:0;"></span>
            {{ $newSelect }}
        </span>
    </div>
</div>
