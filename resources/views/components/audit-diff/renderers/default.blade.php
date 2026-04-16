<div style="display:grid;grid-template-columns:1fr 1fr;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:6px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $oldText !== '' ? $oldText : '—' }}</p>
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:6px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $newText !== '' ? $newText : '—' }}</p>
    </div>
</div>
