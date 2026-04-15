<?php

namespace Androsamp\FilamentResourceLock\Forms\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Add to a custom Filament form field subclass to render HTML previews in the audit diff modal.
 *
 * The closure receives the dehydrated attribute value (same as stored on the model).
 * Return trusted HTML only — escape user-controlled fragments with {@see e()}.
 */
trait HasAuditDiffPreview
{
    protected Closure | null $auditDiffPreviewUsing = null;

    /**
     * @param  Closure(mixed $state, mixed $value): string|Htmlable|null  $callback
     */
    public function auditDiffPreviewUsing(?Closure $callback): static
    {
        $this->auditDiffPreviewUsing = $callback;

        return $this;
    }

    public function getAuditDiffPreviewHtml(mixed $value): ?string
    {
        if ($this->auditDiffPreviewUsing === null) {
            return null;
        }

        try {
            $result = $this->evaluate($this->auditDiffPreviewUsing, [
                'state' => $value,
                'value' => $value,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if ($result === null) {
            return null;
        }

        if ($result instanceof Htmlable) {
            $result = $result->toHtml();
        }

        if (! is_string($result)) {
            return null;
        }

        $trimmed = trim($result);

        return $trimmed === '' ? null : $result;
    }
}
