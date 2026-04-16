<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

class NumericTextAuditDiffRenderer extends AbstractAuditDiffRenderer
{
    public function canRender(array $change): bool
    {
        return ($change['type'] ?? null) === 'TextInput (numeric)';
    }

    public function render(array $change): string
    {
        $oldValue = $change['old'] ?? null;
        $newValue = $change['new'] ?? null;

        $oldNum = is_numeric($oldValue) ? (float) $oldValue : null;
        $newNum = is_numeric($newValue) ? (float) $newValue : null;
        $max = max(abs($oldNum ?? 0), abs($newNum ?? 0)) ?: 1;

        return $this->renderView('filament-resource-lock::components.audit-diff.renderers.numeric-text', [
            'oldText' => $this->stringify($oldValue),
            'newText' => $this->stringify($newValue),
            'oldPct' => $oldNum !== null ? min(100, (int) round(abs($oldNum / $max) * 100)) : 0,
            'newPct' => $newNum !== null ? min(100, (int) round(abs($newNum / $max) * 100)) : 0,
        ]);
    }
}
