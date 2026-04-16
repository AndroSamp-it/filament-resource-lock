<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

use Androsamp\FilamentResourceLock\Support\AuditDiffRenderer;

class SelectAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return ($change['type'] ?? null) === 'Select';
    }

    public function render(array $change): string
    {
        $oldValue = $change['old'] ?? null;
        $newValue = $change['new'] ?? null;

        $oldSource = array_key_exists('old_display', $change) ? $change['old_display'] : $oldValue;
        $newSource = array_key_exists('new_display', $change) ? $change['new_display'] : $newValue;

        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.select', [
            'oldSelect' => AuditDiffRenderer::formatSelectValue($oldSource),
            'newSelect' => AuditDiffRenderer::formatSelectValue($newSource),
        ]);
    }
}
