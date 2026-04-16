@once
    <style>
    .fi-audit-rich-content { font-size:.875rem; line-height:1.6; word-break:break-word; overflow-x:auto; }
    .fi-audit-rich-content > *:first-child { margin-top:0 !important; }
    .fi-audit-rich-content > *:last-child  { margin-bottom:0 !important; }
    .fi-audit-rich-content p { margin:0 0 .5em; }
    .fi-audit-rich-content strong,.fi-audit-rich-content b { font-weight:700; }
    .fi-audit-rich-content em,.fi-audit-rich-content i { font-style:italic; }
    .fi-audit-rich-content u { text-decoration:underline; }
    .fi-audit-rich-content s,.fi-audit-rich-content del { text-decoration:line-through; }
    .fi-audit-rich-content sub { vertical-align:sub; font-size:.75em; }
    .fi-audit-rich-content sup { vertical-align:super; font-size:.75em; }
    .fi-audit-rich-content small { font-size:.8em; }
    .fi-audit-rich-content mark { background:#fef08a; color:#713f12; border-radius:2px; padding:0 2px; }
    .fi-audit-rich-content .lead { font-size:1.1em; font-weight:500; }
    .fi-audit-rich-content h1,.fi-audit-rich-content h2,.fi-audit-rich-content h3,
    .fi-audit-rich-content h4,.fi-audit-rich-content h5,.fi-audit-rich-content h6
        { font-weight:700; margin:.75em 0 .35em; line-height:1.25; }
    .fi-audit-rich-content h1 { font-size:1.6em; }
    .fi-audit-rich-content h2 { font-size:1.35em; }
    .fi-audit-rich-content h3 { font-size:1.15em; }
    .fi-audit-rich-content h4 { font-size:1.05em; }
    .fi-audit-rich-content h5 { font-size:.95em; }
    .fi-audit-rich-content h6 { font-size:.85em; }
    .fi-audit-rich-content a { color:#60a5fa; text-decoration:underline; }
    .fi-audit-rich-content ul { list-style:disc;    padding-left:1.5em; margin:.4em 0; }
    .fi-audit-rich-content ol { list-style:decimal; padding-left:1.5em; margin:.4em 0; }
    .fi-audit-rich-content li { margin:.2em 0; }
    .fi-audit-rich-content li > p { margin:0; }
    .fi-audit-rich-content blockquote
        { border-left:3px solid rgba(148,163,184,.4); padding-left:.85em; margin:.6em 0; color:#94a3b8; }
    .fi-audit-rich-content :not(pre) > code
        { background:rgba(0,0,0,.3); border-radius:3px; padding:1px 5px; font-family:monospace; font-size:.82em; }
    .fi-audit-rich-content pre
        { background:rgba(0,0,0,.35); border-radius:6px; padding:.7em 1em; overflow-x:auto; font-family:monospace; font-size:.82em; margin:.5em 0; }
    .fi-audit-rich-content pre code { background:none; padding:0; border-radius:0; }
    .fi-audit-rich-content hr { border:none; border-top:1px solid rgba(148,163,184,.2); margin:.85em 0; }
    .fi-audit-rich-content > img, .fi-audit-rich-content p > img, .fi-audit-rich-content figure > img { max-width:100%; height:auto; border-radius:4px; }
    .fi-audit-rich-content figure { margin:.5em 0; }
    .fi-audit-rich-content figcaption { font-size:.78em; color:#94a3b8; text-align:center; margin-top:.3em; }
    .fi-audit-rich-content table { width:100%; border-collapse:collapse; margin:.5em 0; font-size:.85em; table-layout:auto; }
    .fi-audit-rich-content th,.fi-audit-rich-content td
        { padding:6px 10px; border:1px solid rgba(148,163,184,.25); text-align:left; vertical-align:top; }
    .fi-audit-rich-content thead th { background:rgba(148,163,184,.15); font-weight:600; }
    .fi-audit-rich-content th { background:rgba(148,163,184,.1); font-weight:600; }
    .fi-audit-rich-content tr:nth-child(even) td { background:rgba(148,163,184,.04); }
    .fi-audit-rich-content colgroup,.fi-audit-rich-content col { display:table-column; }
    .fi-audit-rich-content .grid-layout { display:grid; gap:.75em; margin:.5em 0; }
    .fi-audit-rich-content .grid-layout-col { min-width:0; overflow:hidden; }
    .fi-audit-rich-content details
        { border:1px solid rgba(148,163,184,.2); border-radius:6px; padding:.5em .85em; margin:.5em 0; }
    .fi-audit-rich-content summary
        { font-weight:600; cursor:default; padding:.25em 0; list-style:none; user-select:none; }
    .fi-audit-rich-content summary::-webkit-details-marker { display:none; }
    .fi-audit-rich-content details[open] summary
        { margin-bottom:.4em; border-bottom:1px solid rgba(148,163,184,.15); padding-bottom:.4em; }
    .fi-audit-rich-content [data-type="customBlock"]
        { border:1px dashed rgba(148,163,184,.3); border-radius:6px; padding:.5em .85em; margin:.5em 0; }
    .fi-audit-rich-content [data-type="customBlock"] .fi-icon-btn,
    .fi-audit-rich-content [data-type="customBlock"] [class*="-actions"],
    .fi-audit-rich-content [data-type="customBlock"] [class*="-button"]
        { display:none !important; }
    .fi-audit-rich-content .fi-fo-rich-editor-custom-block-heading
        { font-size:.7em; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.35em; }
    .fi-audit-rich-content [data-type="mergeTag"]
        { background:rgba(96,165,250,.15); color:#93c5fd; border-radius:3px; padding:1px 5px; font-family:monospace; font-size:.85em; }
    .fi-audit-rich-content .fi-fo-rich-editor-mention
        { background:rgba(167,139,250,.15); color:#c4b5fd; border-radius:3px; padding:1px 5px; }
    </style>
@endonce

<div style="display:flex;flex-direction:column;">
    <div style="padding:12px 16px;background:rgba(239,68,68,.07);border-bottom:1px solid rgba(148,163,184,.15);">
        <div style="font-size:.65rem;font-weight:600;color:#f87171;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.was') }}</div>
        <div class="fi-audit-rich-content">{!! $oldRichContent !!}</div>
    </div>
    <div style="padding:12px 16px;background:rgba(34,197,94,.07);">
        <div style="font-size:.65rem;font-weight:600;color:#4ade80;letter-spacing:.06em;margin-bottom:8px;">{{ __('filament-resource-lock::resource-lock.audit.diff.became') }}</div>
        <div class="fi-audit-rich-content">{!! $newRichContent !!}</div>
    </div>
</div>
