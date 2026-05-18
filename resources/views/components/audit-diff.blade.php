@php
    use Androsamp\FilamentResourceLock\Support\AuditDiffRenderer;
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
            $oldValue = $change['old'] ?? null;
            $newValue = $change['new'] ?? null;
            $renderAuditValue = static function (mixed $value): string {
                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
                }

                return (string) ($value ?? '');
            };
            $oldText = $renderAuditValue($oldValue);
            $newText = $renderAuditValue($newValue);
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

            {{-- Toggle --}}
            @if ($type === 'Toggle')
                <div style="display:grid;grid-template-columns:1fr 1fr;">
                    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
                        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            @php $oldOn = (bool)($change['old']); @endphp
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
                            @php $newOn = (bool)($change['new']); @endphp
                            <div style="width:32px;height:18px;border-radius:999px;background:{{ $newOn ? '#6366f1' : '#475569' }};position:relative;flex-shrink:0;">
                                <div style="position:absolute;top:2px;{{ $newOn ? 'right:2px' : 'left:2px' }};width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);"></div>
                            </div>
                            <span style="font-size:.875rem;color:inherit;">
                                {{ $newOn ? __('filament-resource-lock::resource-lock.audit.diff.yes') : __('filament-resource-lock::resource-lock.audit.diff.no') }}
                            </span>
                        </div>
                    </div>
                </div>

            {{-- Select --}}
            @elseif ($type === 'Select')
                @php
                    $oldSelect = array_key_exists('old_display', $change)
                        ? AuditDiffRenderer::formatSelectValue($change['old_display'])
                        : AuditDiffRenderer::formatSelectValue($oldValue);
                    $newSelect = array_key_exists('new_display', $change)
                        ? AuditDiffRenderer::formatSelectValue($change['new_display'])
                        : AuditDiffRenderer::formatSelectValue($newValue);
                @endphp
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

            {{-- KeyValue / JSON --}}
            @elseif ($type === 'KeyValue / JSON')
                @php $jsonDiff = AuditDiffRenderer::jsonDiff($oldValue, $newValue); @endphp
                <div style="padding:10px 14px;background:#0f172a;font-family:monospace;font-size:.8rem;display:flex;flex-direction:column;gap:2px;">
                    @foreach ($jsonDiff as $line)
                        <div style="border-radius:3px;padding:2px 6px;
                            {{ $line['type'] === 'removed' ? 'background:rgba(239,68,68,.2);color:#fca5a5;' : ($line['type'] === 'added' ? 'background:rgba(34,197,94,.2);color:#86efac;' : 'color:#64748b;') }}">
                            {{ $line['type'] === 'removed' ? '- ' : ($line['type'] === 'added' ? '+ ' : '  ') }}{{ $line['line'] }}
                        </div>
                    @endforeach
                </div>

            {{-- RichEditor / MarkdownEditor — render HTML as-is, stacked layout --}}
            @elseif (in_array($type, ['RichEditor', 'MarkdownEditor']))
                @once
                <style>
                /* ─── fi-audit-rich-content: full Filament RichEditor rendering ─── */
                .fi-audit-rich-content { font-size:.875rem; line-height:1.6; word-break:break-word; overflow-x:auto; }
                .fi-audit-rich-content > *:first-child { margin-top:0 !important; }
                .fi-audit-rich-content > *:last-child  { margin-bottom:0 !important; }

                /* Paragraphs */
                .fi-audit-rich-content p { margin:0 0 .5em; }

                /* Inline: bold, italic, underline, strike, sub, sup */
                .fi-audit-rich-content strong,.fi-audit-rich-content b { font-weight:700; }
                .fi-audit-rich-content em,.fi-audit-rich-content i { font-style:italic; }
                .fi-audit-rich-content u { text-decoration:underline; }
                .fi-audit-rich-content s,.fi-audit-rich-content del { text-decoration:line-through; }
                .fi-audit-rich-content sub { vertical-align:sub; font-size:.75em; }
                .fi-audit-rich-content sup { vertical-align:super; font-size:.75em; }
                .fi-audit-rich-content small { font-size:.8em; }

                /* Highlight (<mark>) — default yellow, colour overrides via inline style */
                .fi-audit-rich-content mark { background:#fef08a; color:#713f12; border-radius:2px; padding:0 2px; }

                /* Lead paragraph */
                .fi-audit-rich-content .lead { font-size:1.1em; font-weight:500; }

                /* Headings */
                .fi-audit-rich-content h1,.fi-audit-rich-content h2,.fi-audit-rich-content h3,
                .fi-audit-rich-content h4,.fi-audit-rich-content h5,.fi-audit-rich-content h6
                    { font-weight:700; margin:.75em 0 .35em; line-height:1.25; }
                .fi-audit-rich-content h1 { font-size:1.6em; }
                .fi-audit-rich-content h2 { font-size:1.35em; }
                .fi-audit-rich-content h3 { font-size:1.15em; }
                .fi-audit-rich-content h4 { font-size:1.05em; }
                .fi-audit-rich-content h5 { font-size:.95em; }
                .fi-audit-rich-content h6 { font-size:.85em; }

                /* Links */
                .fi-audit-rich-content a { color:#60a5fa; text-decoration:underline; }

                /* Lists */
                .fi-audit-rich-content ul { list-style:disc;    padding-left:1.5em; margin:.4em 0; }
                .fi-audit-rich-content ol { list-style:decimal; padding-left:1.5em; margin:.4em 0; }
                .fi-audit-rich-content li { margin:.2em 0; }
                .fi-audit-rich-content li > p { margin:0; }

                /* Blockquote */
                .fi-audit-rich-content blockquote
                    { border-left:3px solid rgba(148,163,184,.4); padding-left:.85em; margin:.6em 0; color:#94a3b8; }

                /* Code */
                .fi-audit-rich-content :not(pre) > code
                    { background:rgba(0,0,0,.3); border-radius:3px; padding:1px 5px; font-family:monospace; font-size:.82em; }
                .fi-audit-rich-content pre
                    { background:rgba(0,0,0,.35); border-radius:6px; padding:.7em 1em; overflow-x:auto; font-family:monospace; font-size:.82em; margin:.5em 0; }
                .fi-audit-rich-content pre code { background:none; padding:0; border-radius:0; }

                /* HR */
                .fi-audit-rich-content hr { border:none; border-top:1px solid rgba(148,163,184,.2); margin:.85em 0; }

                /* Images */
                .fi-audit-rich-content > img, .fi-audit-rich-content p > img, .fi-audit-rich-content figure > img { max-width:100%; height:auto; border-radius:4px; }
                .fi-audit-rich-content figure { margin:.5em 0; }
                .fi-audit-rich-content figcaption { font-size:.78em; color:#94a3b8; text-align:center; margin-top:.3em; }

                /* Tables (TipTap TableKit) */
                .fi-audit-rich-content table { width:100%; border-collapse:collapse; margin:.5em 0; font-size:.85em; table-layout:auto; }
                .fi-audit-rich-content th,.fi-audit-rich-content td
                    { padding:6px 10px; border:1px solid rgba(148,163,184,.25); text-align:left; vertical-align:top; }
                .fi-audit-rich-content thead th { background:rgba(148,163,184,.15); font-weight:600; }
                .fi-audit-rich-content th { background:rgba(148,163,184,.1); font-weight:600; }
                .fi-audit-rich-content tr:nth-child(even) td { background:rgba(148,163,184,.04); }
                .fi-audit-rich-content colgroup,.fi-audit-rich-content col { display:table-column; }

                /* Grid layout (extension-grid) — columns set via inline style="grid-template-columns:..." */
                .fi-audit-rich-content .grid-layout { display:grid; gap:.75em; margin:.5em 0; }
                .fi-audit-rich-content .grid-layout-col { min-width:0; overflow:hidden; }

                /* Details / Summary */
                .fi-audit-rich-content details
                    { border:1px solid rgba(148,163,184,.2); border-radius:6px; padding:.5em .85em; margin:.5em 0; }
                .fi-audit-rich-content summary
                    { font-weight:600; cursor:default; padding:.25em 0; list-style:none; user-select:none; }
                .fi-audit-rich-content summary::-webkit-details-marker { display:none; }
                .fi-audit-rich-content details[open] summary
                    { margin-bottom:.4em; border-bottom:1px solid rgba(148,163,184,.15); padding-bottom:.4em; }

                /* Custom block — hide editor controls, show heading + preview */
                .fi-audit-rich-content [data-type="customBlock"]
                    { border:1px dashed rgba(148,163,184,.3); border-radius:6px; padding:.5em .85em; margin:.5em 0; }
                .fi-audit-rich-content [data-type="customBlock"] .fi-icon-btn,
                .fi-audit-rich-content [data-type="customBlock"] [class*="-actions"],
                .fi-audit-rich-content [data-type="customBlock"] [class*="-button"]
                    { display:none !important; }
                .fi-audit-rich-content .fi-fo-rich-editor-custom-block-heading
                    { font-size:.7em; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.35em; }

                /* Merge tag */
                .fi-audit-rich-content [data-type="mergeTag"]
                    { background:rgba(96,165,250,.15); color:#93c5fd; border-radius:3px; padding:1px 5px; font-family:monospace; font-size:.85em; }

                /* Mention */
                .fi-audit-rich-content .fi-fo-rich-editor-mention
                    { background:rgba(167,139,250,.15); color:#c4b5fd; border-radius:3px; padding:1px 5px; }
                </style>
                @endonce
                <div style="display:flex;flex-direction:column;">
                    <div style="padding:12px 16px;background:rgba(239,68,68,.07);border-bottom:1px solid rgba(148,163,184,.15);">
                        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
                        <div class="fi-audit-rich-content">{!! AuditDiffRenderer::renderRichContent($oldValue, $change['customBlocks'] ?? []) !!}</div>
                    </div>
                    <div style="padding:12px 16px;background:rgba(34,197,94,.07);">
                        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
                        <div class="fi-audit-rich-content">{!! AuditDiffRenderer::renderRichContent($newValue, $change['customBlocks'] ?? []) !!}</div>
                    </div>
                </div>

            {{-- Custom field: HTML preview from auditDiffPreviewUsing() --}}
            @elseif (($change['old_preview_html'] ?? null) !== null || ($change['new_preview_html'] ?? null) !== null)
                <div style="display:grid;grid-template-columns:1fr 1fr;">
                    <div style="padding:12px 16px;background:rgba(239,68,68,.07);">
                        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
                        @if (($change['old_preview_html'] ?? null) !== null)
                            <div class="fi-audit-custom-field-preview fi-not-prose" style="font-size:.875rem;line-height:1.5;word-break:break-word;">{!! $change['old_preview_html'] !!}</div>
                        @else
                            <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $oldText !== '' ? $oldText : '—' }}</p>
                        @endif
                    </div>
                    <div style="padding:12px 16px;background:rgba(34,197,94,.07);border-left:1px solid rgba(148,163,184,.15);">
                        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
                        @if (($change['new_preview_html'] ?? null) !== null)
                            <div class="fi-audit-custom-field-preview fi-not-prose" style="font-size:.875rem;line-height:1.5;word-break:break-word;">{!! $change['new_preview_html'] !!}</div>
                        @else
                            <p style="font-size:.875rem;margin:0;white-space:pre-wrap;word-break:break-word;">{{ $newText !== '' ? $newText : '—' }}</p>
                        @endif
                    </div>
                </div>

            {{-- TextInput (numeric) --}}
            @elseif ($type === 'TextInput (numeric)')
                @php
                    $oldNum = is_numeric($oldValue) ? (float) $oldValue : null;
                    $newNum = is_numeric($newValue) ? (float) $newValue : null;
                    $max    = max(abs($oldNum ?? 0), abs($newNum ?? 0)) ?: 1;
                    $oldPct = $oldNum !== null ? min(100, round(abs($oldNum / $max) * 100)) : 0;
                    $newPct = $newNum !== null ? min(100, round(abs($newNum / $max) * 100)) : 0;
                @endphp
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

            {{-- Default: TextInput, Textarea, etc. --}}
            @else
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
            @endif

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
