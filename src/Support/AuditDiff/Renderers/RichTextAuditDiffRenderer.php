<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

use Androsamp\FilamentResourceLock\Support\AuditDiffRenderer;

class RichTextAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return in_array($change['type'] ?? null, ['RichEditor', 'MarkdownEditor'], true);
    }

    public function render(array $change): string
    {
        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.rich-text', [
            'oldRichContent' => AuditDiffRenderer::renderRichContent($change['old'] ?? null, $change['customBlocks'] ?? []),
            'newRichContent' => AuditDiffRenderer::renderRichContent($change['new'] ?? null, $change['customBlocks'] ?? []),
        ]);
    }
}
