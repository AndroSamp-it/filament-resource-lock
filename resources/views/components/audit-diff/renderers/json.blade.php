<div style="padding:10px 14px;background:#0f172a;font-family:monospace;font-size:.8rem;display:flex;flex-direction:column;gap:2px;">
    @foreach ($jsonDiff as $line)
        <div style="border-radius:3px;padding:2px 6px;
            {{ $line['type'] === 'removed' ? 'background:rgba(239,68,68,.2);color:#fca5a5;' : ($line['type'] === 'added' ? 'background:rgba(34,197,94,.2);color:#86efac;' : 'color:#64748b;') }}">
            {{ $line['type'] === 'removed' ? '- ' : ($line['type'] === 'added' ? '+ ' : '  ') }}{{ $line['line'] }}
        </div>
    @endforeach
</div>
