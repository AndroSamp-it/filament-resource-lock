<?php

namespace Androsamp\FilamentResourceLock\Resources\Columns;

use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class ResourceAuditCreatorColumn extends TextColumn
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'resource_audit_creator')
            ->label(__('filament-resource-lock::resource-lock.list.creator'))
            ->state(
                fn (Model $record): ?string => app(ResourceAuditService::class)->getCreatorDisplayName($record)
            )
            ->placeholder('—')
            ->toggleable();
    }
}
