<?php

namespace Androsamp\FilamentResourceLock\Concerns;

use Androsamp\FilamentResourceLock\Actions\ShowAuditHistoryAction;
use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

trait HasResourceAudit
{
    /** Snapshot of the record before the current save. */
    protected array $auditPreviousSnapshot = [];

    public function mountHasResourceAudit(): void
    {
        if (! $this->isAuditEnabled()) {
            return;
        }

        $this->auditPreviousSnapshot = $this->captureAuditSnapshot();
    }

    /**
     * Wrap Filament save flow so audit still works even when a page overrides afterSave().
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if ($this->isAuditEnabled()) {
            // Capture "before" state from current record attributes.
            $this->auditPreviousSnapshot = $this->captureAuditSnapshot();
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->syncResourceAuditAfterSave();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the "История изменений" action to place in the page header.
     * Usage: override getHeaderActions() and include $this->getAuditHistoryAction().
     */
    protected function getAuditHistoryAction(): Action
    {
        return ShowAuditHistoryAction::make($this->getRecord());
    }

    /**
     * Public bridge for custom pages that fully override save()/dehydrate* hooks.
     * Call this after the record is persisted.
     */
    public function syncResourceAuditAfterSave(): void
    {
        if (! $this->isAuditEnabled()) {
            return;
        }

        $this->recordAuditEntry();
    }

    #[On('resourceLock::auditRolledBack')]
    public function refreshResourceAfterAuditRollback(string $lockableType, string $lockableId): void
    {
        if (! method_exists($this, 'getRecord')) {
            return;
        }

        $record = $this->getRecord();

        if (! $record) {
            return;
        }

        if ($record::class !== $lockableType || (string) $record->getKey() !== $lockableId) {
            return;
        }

        $record->refresh();

        if (method_exists($this, 'fillForm')) {
            $this->fillForm();
        } else {
            $this->form->fill($record->attributesToArray());
        }

        $this->auditPreviousSnapshot = $this->captureAuditSnapshot();
    }

    // -------------------------------------------------------------------------
    // Snapshot capture
    // -------------------------------------------------------------------------

    /**
     * Builds a snapshot from the Filament form's current state.
     * Each entry: field_name => {value, label, type}.
     *
     * @return array<string, array{value: mixed, label: string, type: string, display?: string|array|null, customBlocks?: array<int, string>, audit_diff_preview?: string}>
     */
    protected function captureAuditSnapshot(): array
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        if (! $record) {
            return [];
        }

        // Clear loaded relation cache to ensure pivot/multiselect fields are fetched fresh
        $record->unsetRelations();

        try {
            $components = $this->form->getComponents(withHidden: true);
        } catch (\Throwable) {
            return [];
        }

        $snapshot = [];

        foreach ($this->flattenAuditComponents($components) as $component) {
            $name = $component->getName();

            if (! $name || str_contains($name, '.')) {
                // Skip sub-fields of complex components (Repeater, etc.)
                continue;
            }

            $value = $record->{$name} ?? null;

            if ($value instanceof \Illuminate\Support\Collection) {
                $value = $value->map(function ($item) {
                    if ($item instanceof \Illuminate\Database\Eloquent\Model) {
                        $arr = $item->toArray();
                        unset($arr['pivot']);

                        return $arr;
                    }

                    return $item;
                })->toArray();
            } elseif ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $arr = $value->toArray();
                unset($arr['pivot']);
                $value = $arr;
            }

            $snapshot[$name] = [
                'value' => $value,
                'label' => (string) $component->getLabel(),
                'type'  => $this->resolveAuditFieldType($component),
            ];

            if ($component instanceof Select) {
                $snapshot[$name]['display'] = $this->resolveSelectDisplayValue($component, $value, $record);
            }

            if ($component instanceof \Filament\Forms\Components\RichEditor) {
                try {
                    $blocks = $component->getCustomBlocks();
                    $customBlocks = [];
                    foreach ($blocks as $block) {
                        $customBlocks[] = is_object($block) ? get_class($block) : $block;
                    }
                    $snapshot[$name]['customBlocks'] = $customBlocks;
                } catch (\Throwable) {
                    $snapshot[$name]['customBlocks'] = [];
                }
            }

            if (method_exists($component, 'getAuditDiffPreviewHtml')) {
                try {
                    $previewHtml = $component->getAuditDiffPreviewHtml($value);
                    if (is_string($previewHtml) && trim($previewHtml) !== '') {
                        $snapshot[$name]['audit_diff_preview'] = $previewHtml;
                    }
                } catch (\Throwable) {
                    // Ignore preview failures; the raw value is still snapshotted.
                }
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<int, mixed>  $components
     * @return Field[]
     */
    private function flattenAuditComponents(array $components): array
    {
        $fields = [];

        foreach ($components as $component) {
            if ($component instanceof Field) {
                $fields[] = $component;
            } elseif (method_exists($component, 'getChildComponents')) {
                $fields = array_merge($fields, $this->flattenAuditComponents($component->getChildComponents()));
            }
        }

        return $fields;
    }

    private function resolveAuditFieldType(Field $component): string
    {
        return match (true) {
            $component instanceof TextInput     => $component->isNumeric() ? 'TextInput (numeric)' : 'TextInput',
            $component instanceof Select        => 'Select',
            $component instanceof Toggle        => 'Toggle',
            $component instanceof RichEditor    => 'RichEditor',
            $component instanceof MarkdownEditor => 'MarkdownEditor',
            $component instanceof KeyValue      => 'KeyValue / JSON',
            $component instanceof Textarea      => 'Textarea',
            $component instanceof ColorPicker   => 'ColorPicker',
            $component instanceof DateTimePicker => 'DateTimePicker',
            $component instanceof DatePicker    => 'DatePicker',
            $component instanceof TimePicker    => 'TimePicker',
            default                             => class_basename($component),
        };
    }

    private function resolveSelectDisplayValue(Select $component, mixed $value, Model $record): string|array|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($component->hasRelationship()) {
            try {
                $relationship = $component->getRelationship();
            } catch (\Throwable) {
                $relationship = null;
            }

            if ($relationship) {
                $related = $relationship->getRelated();
                $keyName = $related->getKeyName();
                $titleAttribute = $component->getRelationshipTitleAttribute() ?: $keyName;
                $ids = $this->extractSelectIds($value, $keyName);

                if ($ids === []) {
                    return null;
                }

                $records = $related->newQuery()
                    ->whereIn($keyName, $ids)
                    ->get();

                $labelsById = [];

                foreach ($records as $relatedRecord) {
                    if (! $relatedRecord instanceof Model) {
                        continue;
                    }

                    $labelsById[(string) $relatedRecord->getKey()] = $component->hasOptionLabelFromRecordUsingCallback()
                        ? $component->getOptionLabelFromRecord($relatedRecord)
                        : (string) ($relatedRecord->{$titleAttribute} ?? $relatedRecord->getKey());
                }

                if (is_array($value)) {
                    $labels = [];

                    foreach ($ids as $id) {
                        $labels[] = $labelsById[(string) $id] ?? (string) $id;
                    }

                    return $labels;
                }

                return $labelsById[(string) $value] ?? (string) $value;
            }
        }

        $options = $component->getOptions();
        $flatOptions = [];

        foreach ($options as $optionValue => $optionLabel) {
            if (is_array($optionLabel)) {
                foreach ($optionLabel as $nestedValue => $nestedLabel) {
                    $flatOptions[(string) $nestedValue] = (string) $nestedLabel;
                }

                continue;
            }

            $flatOptions[(string) $optionValue] = (string) $optionLabel;
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $id): string => $flatOptions[(string) $id] ?? (string) $id,
                $this->extractSelectIds($value),
            );
        }

        return $flatOptions[(string) $value] ?? (string) $value;
    }

    /**
     * @return array<int, int|string>
     */
    private function extractSelectIds(mixed $value, string $keyName = 'id'): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $item) {
            if (is_scalar($item)) {
                if ($item !== '') {
                    $ids[] = $item;
                }

                continue;
            }

            if (is_array($item)) {
                $candidate = $item[$keyName] ?? $item['id'] ?? null;

                if (is_scalar($candidate) && $candidate !== '') {
                    $ids[] = $candidate;
                }

                continue;
            }

            if ($item instanceof Model) {
                $candidate = $item->getAttribute($keyName) ?? $item->getKey();

                if (is_scalar($candidate) && $candidate !== '') {
                    $ids[] = $candidate;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    private function recordAuditEntry(): void
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        if (! $record) {
            return;
        }

        $newSnapshot = $this->captureAuditSnapshot();
        $service     = app(ResourceAuditService::class);
        $oldSnapshot = ! empty($this->auditPreviousSnapshot)
            ? $this->auditPreviousSnapshot
            : $service->getLatestSnapshot($record);
        $changes     = $service->computeChanges($oldSnapshot, $newSnapshot);

        if (empty($changes)) {
            return;
        }

        $lockCycleId = $this->resolveCurrentLockCycleId();

        $service->recordSnapshot(
            record:             $record,
            lockCycleId:        $lockCycleId ?? 'manual',
            actorUserId:        auth()->id(),
            actorSessionId:     session()->getId(),
            actorDisplayName:   $this->resolveAuditActorName(),
            snapshot:           $newSnapshot,
            changes:            $changes,
        );

        // Update baseline so the next save computes delta from the current state.
        $this->auditPreviousSnapshot = $newSnapshot;
    }

    private function resolveCurrentLockCycleId(): ?string
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        if (! $record) {
            return null;
        }

        // If the page uses InteractsWithResourceLock, get cycle id from the active lock.
        if (property_exists($this, 'resourceLockSessionId')) {
            return app(ResourceAuditService::class)->getCurrentLockCycleId($record);
        }

        return null;
    }

    private function resolveAuditActorName(): string
    {
        $user   = auth()->user();
        $column = (string) config('filament-resource-lock.user_display_column', 'name');

        if (! $user) {
            return __('filament-resource-lock::resource-lock.other_user');
        }

        return (string) ($user->{$column} ?? $user->getKey());
    }

    private function isAuditEnabled(): bool
    {
        return (bool) config('filament-resource-lock.audit.enabled', true);
    }
}
