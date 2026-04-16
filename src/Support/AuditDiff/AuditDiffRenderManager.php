<?php

namespace Androsamp\FilamentResourceLock\Support\AuditDiff;

use Androsamp\FilamentResourceLock\Support\AuditDiff\Contracts\AuditDiffFieldRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\CustomPreviewAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\DefaultAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\JsonAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\NumericTextAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\RichTextAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\SelectAuditDiffRenderer;
use Androsamp\FilamentResourceLock\Support\AuditDiff\Renderers\ToggleAuditDiffRenderer;
use RuntimeException;

class AuditDiffRenderManager
{
    /** @var array<int, AuditDiffFieldRenderer> */
    private array $renderers;

    public function __construct()
    {
        $this->renderers = [
            new ToggleAuditDiffRenderer(),
            new SelectAuditDiffRenderer(),
            new JsonAuditDiffRenderer(),
            new RichTextAuditDiffRenderer(),
            new CustomPreviewAuditDiffRenderer(),
            new NumericTextAuditDiffRenderer(),
            new DefaultAuditDiffRenderer(),
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    public function render(array $change): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($change)) {
                return $renderer->render($change);
            }
        }

        throw new RuntimeException('No renderer found for audit diff change.');
    }
}
