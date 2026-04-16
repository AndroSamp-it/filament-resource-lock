<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff\Contracts;

interface AuditDiffFieldRenderer
{
    /**
     * @param  array<string, mixed>  $change
     */
    public function canRender(array $change): bool;

    /**
     * @param  array<string, mixed>  $change
     */
    public function render(array $change): string;
}
