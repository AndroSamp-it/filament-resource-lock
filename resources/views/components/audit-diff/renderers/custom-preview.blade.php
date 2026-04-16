<div style="display:grid;grid-template-columns:1fr 1fr;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        @if ($oldPreviewHtml !== null)
            <div class="fi-audit-custom-field-preview fi-not-prose" style="font-size:.875rem;line-height:1.5;word-break:break-word;">{!! $oldPreviewHtml !!}</div>
        @else
            <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $oldText !== '' ? $oldText : '—' }}</p>
        @endif
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        @if ($newPreviewHtml !== null)
            <div class="fi-audit-custom-field-preview fi-not-prose" style="font-size:.875rem;line-height:1.5;word-break:break-word;">{!! $newPreviewHtml !!}</div>
        @else
            <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $newText !== '' ? $newText : '—' }}</p>
        @endif
    </div>
</div>
