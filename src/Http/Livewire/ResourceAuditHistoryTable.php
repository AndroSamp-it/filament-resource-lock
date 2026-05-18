<?php

namespace Androsamp\FilamentResourceLock\Http\Livewire;

use Androsamp\FilamentResourceLock\Models\ResourceLockAudit;
use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ResourceAuditHistoryTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public string $lockableType;
    public int|string $lockableId;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ResourceLockAudit::query()
                    ->where('lockable_type', $this->lockableType)
                    ->where('lockable_id', $this->lockableId)
                    ->whereNotNull('changes')
                    ->whereNotNull('version')
                    ->orderByDesc('version'),
            )
            ->columns([
                TextColumn::make('version')
                    ->label(__('filament-resource-lock::resource-lock.audit.columns.version'))
                    ->formatStateUsing(fn (int $state): string => 'v' . $state)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('filament-resource-lock::resource-lock.audit.columns.date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('actor_display_name')
                    ->label(__('filament-resource-lock::resource-lock.audit.columns.author'))
                    ->default(__('filament-resource-lock::resource-lock.other_user')),

                TextColumn::make('changes_count')
                    ->label(__('filament-resource-lock::resource-lock.audit.columns.changes'))
                    ->getStateUsing(fn (ResourceLockAudit $record): int => count($record->changes ?? []))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('lock_cycle_id')
                    ->label(__('filament-resource-lock::resource-lock.audit.columns.lock_cycle'))
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 8) . '…' : '—')
                    ->tooltip(fn (?string $state): string => $state ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('view_diff')
                    ->label(__('filament-resource-lock::resource-lock.audit.actions.view_diff'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(
                        fn (ResourceLockAudit $record) => __('filament-resource-lock::resource-lock.audit.diff.modal_heading', ['version' => $record->version])
                    )
                    ->modalContent(
                        fn (ResourceLockAudit $record) => view('filament-resource-lock::components.audit-diff', ['audit' => $record])
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-resource-lock::resource-lock.audit.close')),
                Action::make('rollback_changes')
                    ->label(__('filament-resource-lock::resource-lock.audit.actions.rollback_changes'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form([
                        CheckboxList::make('fields')
                            ->label(__('filament-resource-lock::resource-lock.audit.rollback.fields_label'))
                            ->options(function (ResourceLockAudit $record): array {
                                $options = [];

                                foreach ($record->changes ?? [] as $change) {
                                    $field = (string) ($change['field'] ?? '');

                                    if ($field === '') {
                                        continue;
                                    }

                                    $options[$field] = (string) ($change['label'] ?? $field);
                                }

                                return $options;
                            })
                            ->required(),
                    ])
                    ->modalHeading(
                        fn (ResourceLockAudit $record) => __('filament-resource-lock::resource-lock.audit.rollback.modal_heading', ['version' => $record->version])
                    )
                    ->modalDescription(__('filament-resource-lock::resource-lock.audit.rollback.modal_description'))
                    ->modalSubmitActionLabel(__('filament-resource-lock::resource-lock.audit.rollback.submit'))
                    ->action(function (ResourceLockAudit $record, array $data): void {
                        $fields = array_values(array_filter((array) ($data['fields'] ?? []), static fn (mixed $value): bool => is_string($value) && $value !== ''));

                        if (empty($fields)) {
                            Notification::make()
                                ->danger()
                                ->title(__('filament-resource-lock::resource-lock.audit.rollback.errors.empty_selection'))
                                ->send();

                            return;
                        }

                        try {
                            $displayColumn = (string) config('filament-resource-lock.user_display_column', 'name');

                            app(ResourceAuditService::class)->rollbackFieldsFromAudit(
                                audit: $record,
                                fields: $fields,
                                actorUserId: auth()->id(),
                                actorSessionId: session()->getId(),
                                actorDisplayName: (string) (data_get(auth()->user(), $displayColumn) ?? __('filament-resource-lock::resource-lock.other_user')),
                            );

                            Notification::make()
                                ->success()
                                ->title(__('filament-resource-lock::resource-lock.audit.rollback.success', ['count' => count($fields)]))
                                ->send();

                            $this->dispatch(
                                'resourceLock::auditRolledBack',
                                lockableType: $record->lockable_type,
                                lockableId: (string) $record->lockable_id,
                            );
                        } catch (\Throwable) {
                            Notification::make()
                                ->danger()
                                ->title(__('filament-resource-lock::resource-lock.audit.rollback.errors.failed'))
                                ->send();
                        }
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->heading(__('filament-resource-lock::resource-lock.audit.table_heading'))
            ->emptyStateHeading(__('filament-resource-lock::resource-lock.audit.empty_state_heading'))
            ->emptyStateDescription(__('filament-resource-lock::resource-lock.audit.empty_state_description'));
    }

    public function render(): View
    {
        return view('filament-resource-lock::livewire.resource-audit-history-table');
    }
}
