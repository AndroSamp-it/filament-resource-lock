<div style="display:grid;grid-template-columns:1fr 1fr;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:6px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        <p style="font-size:.9rem;font-weight:600;margin:0 0 8px;">{{ $oldText !== '' ? $oldText : '—' }}</p>
        <div style="height:5px;background:rgba(148,163,184,.2);border-radius:999px;overflow:hidden;">
            <div style="height:100%;width:{{ $oldPct }}%;background:#f87171;border-radius:999px;"></div>
        </div>
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:6px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        <p style="font-size:.9rem;font-weight:600;margin:0 0 8px;">{{ $newText !== '' ? $newText : '—' }}</p>
        <div style="height:5px;background:rgba(148,163,184,.2);border-radius:999px;overflow:hidden;">
            <div style="height:100%;width:{{ $newPct }}%;background:#4ade80;border-radius:999px;"></div>
        </div>
    </div>
</div>
