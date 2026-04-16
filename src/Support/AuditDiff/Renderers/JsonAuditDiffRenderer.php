<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

use Androsamp\FilamentResourceLock\Support\AuditDiffRenderer;

class JsonAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return ($change['type'] ?? null) === 'KeyValue / JSON';
    }

    public function render(array $change): string
    {
        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.json', [
            'jsonDiff' => AuditDiffRenderer::jsonDiff($change['old'] ?? null, $change['new'] ?? null),
        ]);
    }
}
