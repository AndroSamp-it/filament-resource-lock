<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

class RichTextAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return in_array($change['type'] ?? null, ['RichEditor', 'MarkdownEditor'], true);
    }

    public function render(array $change): string
    {
        $payload = json_encode([
            'field' => $change['field'] ?? '',
            'type' => $change['type'] ?? '',
            'old' => $change['old'] ?? null,
            'new' => $change['new'] ?? null,
        ], JSON_UNESCAPED_UNICODE) ?: '';

        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.rich-text', [
            'editorType' => (string) ($change['type'] ?? 'RichEditor'),
            'oldValue' => $change['old'] ?? null,
            'newValue' => $change['new'] ?? null,
            'customBlockClasses' => $change['customBlocks'] ?? [],
            'livewireKey' => 'rl-audit-rich-'.md5($payload),
        ]);
    }
}
