<?php

namespace Androsamp\FilamentResourceLock\Resources\Columns;

use Androsamp\FilamentResourceLock\Models\ResourceLock;
use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model;

class ResourceLockColumn extends IconColumn
{
    public static function make(string|null $name = null): static
    {
        return parent::make($name ?? 'resource_lock')
            ->label('')
            ->state(fn(Model $record): bool => app(ResourceLockManager::class)->findActiveLock($record) !== null)
            ->icon(fn(bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
            ->color(fn(bool $state): string => $state ? 'danger' : 'success')
            ->tooltip(function (Model $record): ?string {
                $lock = app(ResourceLockManager::class)->findActiveLock($record);

                if (! $lock) {
                    return null;
                }

                return __('filament-resource-lock::resource-lock.locked_by', [
                    'user' => static::resolveDisplayName($lock),
                ]);
            });
    }

    protected static function resolveDisplayName(ResourceLock $lock): string
    {
        // Redis mode: display name is stored in the payload.
        $fromPayload = $lock->getAttribute('user_display_name');
        if (! empty($fromPayload)) {
            return (string) $fromPayload;
        }

        // Database mode: resolve via the user relation.
        $user = $lock->relationLoaded('user') ? $lock->user : $lock->user()->first();
        $column = (string) config('filament-resource-lock.user_display_column', 'name');

        if (! $user) {
            return (string) __('filament-resource-lock::resource-lock.other_user');
        }

        return (string) ($user->{$column} ?? $user->getKey() ?? __('filament-resource-lock::resource-lock.other_user'));
    }
}
