<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

class DefaultAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return true;
    }

    public function render(array $change): string
    {
        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.default', [
            'oldText' => $this->stringify($change['old'] ?? null),
            'newText' => $this->stringify($change['new'] ?? null),
        ]);
    }
}
