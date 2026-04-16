<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

class CustomPreviewAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return ($change['old_preview_html'] ?? null) !== null || ($change['new_preview_html'] ?? null) !== null;
    }

    public function render(array $change): string
    {
        $oldText = $this->stringify($change['old'] ?? null);
        $newText = $this->stringify($change['new'] ?? null);

        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.custom-preview', [
            'oldPreviewHtml' => $change['old_preview_html'] ?? null,
            'newPreviewHtml' => $change['new_preview_html'] ?? null,
            'oldText' => $oldText,
            'newText' => $newText,
        ]);
    }
}
