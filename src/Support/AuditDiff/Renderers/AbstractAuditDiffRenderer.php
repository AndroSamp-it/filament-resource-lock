<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers;

use Androsamp\FilamentResourceLock\Support\AuditDiff\Contracts\AuditDiffFieldRenderer;

abstract class AbstractAuditDiffRenderer implements AuditDiffFieldRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $view, array $data): string
    {
        return (string) view($view, $data)->render();
    }

    protected function stringify(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        return (string) ($value ?? '');
    }
}
