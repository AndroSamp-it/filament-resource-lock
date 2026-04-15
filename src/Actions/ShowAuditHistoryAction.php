<?php

namespace Androsamp\FilamentResourceLock\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class ShowAuditHistoryAction
{
    /**
     * Creates a Filament header action that opens the audit history slide-over.
     */
    public static function make(Model $record): Action
    {
        return Action::make('audit_history')
            ->label(__('filament-resource-lock::resource-lock.audit.action_label'))
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->slideOver()
            ->modalWidth('4xl')
            ->modalHeading(__('filament-resource-lock::resource-lock.audit.modal_heading'))
            ->modalContent(
                view('filament-resource-lock::components.audit-history', [
                    'lockableType' => $record::class,
                    'lockableId'   => $record->getKey(),
                ])
            )
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-resource-lock::resource-lock.audit.close'));
    }
}
