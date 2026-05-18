# Filament Resource Lock

Record-level locking for **Filament v5** edit pages with optional **audit history**.

When one user edits a record, others immediately see who owns the lock, get blocked from accidental overwrite, and can request handoff. The package supports both classic polling and Laravel Echo push updates.

## Preview

<p align="center">
  <img src="https://raw.githubusercontent.com/AndroSamp-it/filament-resource-lock/refs/heads/main/plugin-preview.png" alt="Filament Resource Lock preview" width="1100">
</p>

<p align="center">
  Lock state, collaboration actions, and audit history in one flow.
</p>

- **PHP:** `^8.3`
- **Laravel:** `^12.0 || ^13.0`
- **Filament:** `^5.0`
- **Packagist:** [`androsamp/filament-resource-lock`](https://packagist.org/packages/androsamp/filament-resource-lock)

---

## Contents

- [Why this package](#why-this-package)
- [Install](#install)
- [Quick start (3 steps)](#quick-start-3-steps)
- [Configuration](#configuration)
- [Broadcast mode (Laravel Echo)](#broadcast-mode-laravel-echo)
- [Audit history (snapshots + rollback)](#audit-history-snapshots--rollback)
- [Custom fields in audit diff](#custom-fields-in-audit-diff)
- [Soft release route](#soft-release-route)
- [Localization](#localization)
- [Development notes](#development-notes)
- [License](#license)

---

## Why this package

### Main goals

- **Safe collaborative editing** on `EditRecord` pages.
- **Predictable behavior in SPA (`wire:navigate`)**.
- **Simple integration** without extra panel plugin registration.
- **Configurable transport and storage** (`heartbeat` / `broadcast`, `database` / `redis`).
- **Optional built-in audit** with visual per-field diff and selective rollback.

### Key behavior

- User A opens record -> lock is acquired (or refreshed).
- User B opens same record -> form/save are disabled, lock owner is shown.
- User B can request unlock (`ask_to_unblock`), if enabled.
- User A can save and hand over lock (`save_and_unlock`), if enabled.
- In `broadcast` mode, updates are pushed via Echo with lower latency than polling.

---

## Install

```bash
composer require androsamp/filament-resource-lock
php artisan filament-resource-lock:install
php artisan migrate
```

`filament-resource-lock:install` does the following:

- publishes `config/filament-resource-lock.php`;
- publishes package migrations (locks + audit tables);
- publishes `resources/js/filament-resource-lock/echo.js`;
- injects `import './filament-resource-lock/echo';` into `resources/js/bootstrap.js` (if missing).

### Publish only specific resources

```bash
php artisan vendor:publish --tag=filament-resource-lock-config
php artisan vendor:publish --tag=filament-resource-lock-migrations
php artisan vendor:publish --tag=filament-resource-lock-assets
```

---

## Quick start (3 steps)

### 1) Add lock relation to model

```php
use Androsamp\FilamentResourceLock\Concerns\HasResourceLocks;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasResourceLocks;
}
```

### 2) Add lock behavior to `EditRecord` page

```php
use Androsamp\FilamentResourceLock\Concerns\InteractsWithResourceLock;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use InteractsWithResourceLock;

    protected static string $resource = CustomerResource::class;
}
```

### 3) Show lock indicator in list table

```php
use Androsamp\FilamentResourceLock\Resources\Columns\ResourceLockColumn;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table->columns([
        ResourceLockColumn::make(),
        // ...
    ]);
}
```

---

## Configuration

All options live in `config/filament-resource-lock.php`.

### Most important keys

- `update_driver`: `heartbeat` or `broadcast`.
- `storage.driver`: `database` or `redis`.
- `ttl_seconds`: lock expiration window without heartbeat.
- `release_grace_seconds`: grace period for soft release in broadcast flow.
- `stale_soft_release_ignore_seconds`: protection from stale unload pings.
- `user_model`: lock owner model class.
- `user_display_column`: attribute shown in UI and notifications.
- `permission.save_and_unlock.*`: enable/guard transfer action.
- `permission.ask_to_unblock.*`: enable/guard unlock request action.
- `audit.*`: audit feature toggles and retention.

### Permissions

By default, actions use `auth()->user()?->can(...)`:

- `filament-resource-lock.save_and_unlock`
- `filament-resource-lock.ask_to_unblock`

Set permission to `null` to skip policy check for that action.

### Example config skeleton

```php
return [
    'update_driver' => 'heartbeat', // heartbeat | broadcast

    'storage' => [
        'driver' => 'database', // database | redis
    ],

    'ttl_seconds' => 20,
    'release_grace_seconds' => 3,

    'permission' => [
        'save_and_unlock' => [
            'enabled' => true,
            'permission' => 'filament-resource-lock.save_and_unlock',
        ],
        'ask_to_unblock' => [
            'enabled' => true,
            'permission' => 'filament-resource-lock.ask_to_unblock',
        ],
    ],

    'audit' => [
        'enabled' => true,
        'table' => 'resource_lock_audits',
        'max_entries_per_resource' => 500,
    ],
];
```

---

## Broadcast mode (Laravel Echo)

`heartbeat` checks state on interval (for example, every 10 seconds).  
`broadcast` pushes updates through private channels, so lock changes and notifications arrive almost instantly.

### Setup checklist

1. Configure Laravel broadcasting (Reverb/Pusher/Ably/etc.) per official docs.
2. Make sure frontend exposes `window.Echo` with `private()`.
3. Keep published `resources/js/filament-resource-lock/echo.js` aligned with your broker/env setup.
4. Set:

```php
'update_driver' => 'broadcast'
```

5. Optionally tune:
   - `transports.broadcast.channel_prefix`
   - `transports.broadcast.event`
   - `transports.broadcast.renew_interval_seconds`

Official guide: [Laravel Broadcasting](https://laravel.com/docs/broadcasting)

---

## Audit history (snapshots + rollback)

The package can store versioned snapshots of form state and render visual per-field diffs.

### What happens on save

1. Snapshot of previous state is captured.
2. New snapshot is captured after save.
3. Changed fields are computed (`old` vs `new`).
4. A new audit version is stored in `resource_lock_audits`.
5. If limit is exceeded, oldest rows are pruned (`audit.max_entries_per_resource`).

### Add audit to `EditRecord`

```php
use Androsamp\FilamentResourceLock\Concerns\HasResourceAudit;
use Androsamp\FilamentResourceLock\Concerns\InteractsWithResourceLock;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use InteractsWithResourceLock;
    use HasResourceAudit;

    protected function getHeaderActions(): array
    {
        return [
            // ... other actions
            $this->getAuditHistoryAction(),
        ];
    }
}
```

`HasResourceAudit` works standalone, but together with lock trait it groups entries by `lock_cycle_id`.

### Overriding `save()`

The trait defines `save()` that calls `syncResourceAuditBeforeSave()`, then `parent::save()`, then `syncResourceAuditAfterSave()`. In PHP, a `save()` method on your page class **replaces** the trait’s method entirely, so that wrapper is skipped unless you repeat it.

If you override `save()`, keep audit working by invoking the same two bridges around your persistence (typically `parent::save()`):

```php
public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
{
    $this->syncResourceAuditBeforeSave();

    parent::save($shouldRedirect, $shouldSendSavedNotification);

    $this->syncResourceAuditAfterSave();
}
```

If your implementation does not call `parent::save()`, call `syncResourceAuditBeforeSave()` **before** the record is written and `syncResourceAuditAfterSave()` **after** it is successfully persisted (and only then).

There is **no** Filament or PHP mechanism in this package that can inject “always run before/after save” when the page replaces `save()` with a completely custom flow: alternatives such as `beforeSave()` / `afterSave()` suffer from the same issue if those methods are overridden on the page. Until a better integration exists, the explicit calls above are the supported approach.

### Rollback selected fields

From the audit history slide-over:

- open a version;
- choose fields via checkbox list;
- apply rollback.

The service restores selected field values and creates a **new** audit version representing rollback changes.

### Supported diff renderers

- `TextInput` -> plain before/after.
- `TextInput (numeric)` -> before/after + proportional bar.
- `Select` -> label-aware badge diff.
- `Toggle` -> visual on/off diff.
- `RichEditor`, `MarkdownEditor` -> rendered rich content diff blocks.
- `KeyValue / JSON` -> unified `+/-` style lines.
- Other fields (`Textarea`, `DatePicker`, etc.) -> plain before/after.

---

## Custom fields in audit diff

For custom Filament fields, add `HasAuditDiffPreview` to provide custom HTML previews in history modal.

```php
use Androsamp\FilamentResourceLock\Forms\Concerns\HasAuditDiffPreview;
use Filament\Forms\Components\Field;

class MapPicker extends Field
{
    use HasAuditDiffPreview;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditDiffPreviewUsing(function (mixed $state): string {
            $lat = is_array($state) ? ($state['lat'] ?? '-') : '-';
            $lng = is_array($state) ? ($state['lng'] ?? '-') : '-';

            return '<p class="text-sm">' . e($lat) . ', ' . e($lng) . '</p>';
        });
    }
}
```

Security note: callback output is rendered as trusted HTML. Always escape user-controlled fragments.

---

## Soft release route

The package registers signed route `filament-resource-lock.release` (`web`, `signed` middleware).

In broadcast flow it is used on tab close / SPA leave:

- lock is marked as releasing for a short grace period;
- same session can quickly reclaim after refresh;
- other sessions respect grace window.

Make sure `APP_URL` is correct, otherwise signed URL validation may fail.

---

## Localization

Translations are loaded from:

- `filament-resource-lock::resource-lock.*`

Included locales:

- `en`
- `ru`

---

## Development notes

If package is connected via local `path` repository in monorepo, after code changes it is usually enough to run:

```bash
composer dump-autoload
```

---

## License

MIT.
