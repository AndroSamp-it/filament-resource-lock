@php
    use Androsamp\FilamentResourceLock\Support\AuditDiff\AuditDiffRenderManager;

    $renderManager = app(AuditDiffRenderManager::class);
@endphp

<div style="font-family:inherit;">
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding-bottom:14px;margin-bottom:4px;border-bottom:1px solid rgba(148,163,184,.2);">
        <div>
            <div style="font-size:.875rem;font-weight:600;color:inherit;margin-bottom:2px;">
                {{ __('filament-resource-lock::resource-lock.audit.diff.heading') }}
            </div>
            <div style="font-size:.75rem;color:#94a3b8;">
                {{ __('filament-resource-lock::resource-lock.audit.diff.snapshot_date', [
                    'date'   => $audit->created_at?->translatedFormat('d M Y'),
                    'author' => $audit->actor_display_name ?? __('filament-resource-lock::resource-lock.other_user'),
                ]) }}
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span style="display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:600;background:#1e40af;color:#bfdbfe;">
                v{{ $audit->version }}
            </span>
            <span style="display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:500;background:rgba(100,116,139,.15);color:#94a3b8;">
                {{ count($audit->changes ?? []) }}&nbsp;{{ __('filament-resource-lock::resource-lock.audit.diff.changes_count') }}
            </span>
        </div>
    </div>

    {{-- Changed fields --}}
    <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
    @foreach ($audit->changes ?? [] as $change)
        @php
            $type = $change['type'] ?? 'TextInput';
            $bodyHtml = $renderManager->render($change);
        @endphp

        <div style="border-radius:10px;border:1px solid rgba(148,163,184,.2);overflow:hidden;">

            {{-- Field label + type badge --}}
            <div style="display:flex;align-items:center;gap:8px;padding:7px 14px;background:rgba(100,116,139,.08);border-bottom:1px solid rgba(148,163,184,.15);">
                <span style="font-size:.65rem;font-weight:700;letter-spacing:.07em;color:#94a3b8;text-transform:uppercase;">
                    {{ $change['label'] ?? $change['field'] }}
                </span>
                <span style="font-size:.65rem;padding:1px 7px;border-radius:4px;background:rgba(100,116,139,.18);color:#94a3b8;font-family:monospace;">
                    {{ $type }}
                </span>
            </div>
            {!! $bodyHtml !!}

        </div>
    @endforeach
    </div>

    {{-- Footer --}}
    <div style="margin-top:16px;padding-top:10px;border-top:1px solid rgba(148,163,184,.15);display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:.72rem;color:#64748b;">
            {{ __('filament-resource-lock::resource-lock.audit.diff.snapshot_meta', [
                'date'   => $audit->created_at?->translatedFormat('d M Y'),
                'author' => $audit->actor_display_name ?? __('filament-resource-lock::resource-lock.other_user'),
            ]) }}
        </span>
        @if ($audit->lock_cycle_id)
            <span style="font-size:.7rem;font-family:monospace;color:#475569;">{{ substr($audit->lock_cycle_id, 0, 8) }}…</span>
        @endif
    </div>
</div>
