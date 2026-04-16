<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

class ToggleAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return ($change['type'] ?? null) === 'Toggle';
    }

    public function render(array $change): string
    {
        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.toggle', [
            'oldOn' => (bool) ($change['old'] ?? null),
            'newOn' => (bool) ($change['new'] ?? null),
        ]);
    }
}
