# Filament Resource Lock

Record locking on **Filament v5** edit pages: while one user holds the lock, others see a warning, can request a release, or (with permission) save and hand the lock over. The package focuses on **Livewire heartbeat**, reliable behavior in **Filament SPA mode**, and optional **push updates via Laravel Echo**.

- **PHP** `^8.3`
- **Laravel** `^12.0 || ^13.0`
- **Filament** `^5.0`

Packagist: [`androsamp/filament-resource-lock`](https://packagist.org/packages/androsamp/filament-resource-lock).

### Filament compatibility

Only **Filament v5** is supported (see `composer.json`). There is **no full support for older major versions** (e.g. **Filament v3** or **v4**), and none is planned on this package branch: panel APIs, form schemas, and render hooks differ; for legacy stacks use packages built for those versions (including [kenepa/resource-lock](https://github.com/kenepa/resource-lock) and compatible forks).

### Roadmap

Functionality **will be expanded** as needed (extra locking flows, integrations, admin ergonomics, etc.). The current surface is intentionally small; backward compatibility will be preserved where reasonable, with breaking changes handled via semantic versioning and release notes.

---

## Why this package when [blendbyte/filament-resource-lock](https://github.com/blendbyte/filament-resource-lock) exists?

[blendbyte/filament-resource-lock](https://github.com/blendbyte/filament-resource-lock) is a mature fork of [kenepa/resource-lock](https://github.com/kenepa/resource-lock) with a panel plugin, audit trail, lock manager UI, and SPA polling. This package is **narrower in scope** by design but differs in **architecture and priorities**:

| Aspect | This package | blendbyte / kenepa line |
|--------|--------------|-------------------------|
| **Integration** | Auto-registered `ServiceProvider`, no `ResourceLockPlugin::make()` | Plugin must be registered on the `Panel` |
| **Lock storage** | **Database or Redis** (`config` → `storage.driver`) | Primarily a DB table |
| **Update delivery** | **`heartbeat`** — poll every `heartbeat_seconds`; **`broadcast`** — Echo events **right after** server-side changes, so **notifications and state updates arrive faster** than with heartbeat alone | Includes SPA polling; push is not a first-class “driver” |
| **Filament SPA + `wire:navigate`** | Heartbeat via **Alpine `setInterval`** and **global** `Livewire.dispatch` so polling does not stop or target the wrong component; lock release on leave including **`x-destroy`** | Handled via plugin polling options |
| **Soft release on tab close** | Signed route + **grace period**: same session can reclaim quickly after reload | Emphasis on timeout and scheduler |
| **Collaboration** | Built-in **“Request unlock”** / **“Save and release”** with notifications to the lock owner | Read-only, force-unlock, events; different UX |
| **Audit, lock manager UI, scheduled `clear-expired`** | Not included (minimal footprint) | Available in the blendbyte ecosystem |

**Summary:** choose this package if you want **Redis**, **Echo with less reliance on polling**, predictable **SPA** behavior, and **lightweight setup without a panel plugin**. Prefer [blendbyte/filament-resource-lock](https://github.com/blendbyte/filament-resource-lock) if you need **audit**, an **admin screen for all locks**, and the familiar **kenepa/blendbyte** API.

---

## Installation

```bash
composer require androsamp/filament-resource-lock
```

Run the install command and migrate:

```bash
php artisan filament-resource-lock:install
php artisan migrate
```

The command will:

- publish `config/filament-resource-lock.php`;
- publish the resource locks table migration;
- copy `resources/js/filament-resource-lock/echo.js` with Laravel Echo bootstrap;
- add `import './filament-resource-lock/echo';` to `resources/js/bootstrap.js` if the line is missing.

You can publish individual tags only:

```bash
php artisan vendor:publish --tag=filament-resource-lock-config
php artisan vendor:publish --tag=filament-resource-lock-migrations
php artisan vendor:publish --tag=filament-resource-lock-assets
```

### Configuring `config/filament-resource-lock.php`

Review at least:

- **`user_model`** — user class (default `App\Models\User`).
- **`user_display_column`** — attribute for “Locked by …” in the UI and in the Redis payload.
- **`storage.driver`** — `database` or `redis` (Redis must be configured in Laravel).
- **`update_driver`** — `heartbeat` or `broadcast`.
- **`ttl_seconds`**, **`release_grace_seconds`**, **`transports.*`** — heartbeat / renewal / post-unload grace timings.

Permissions for modal actions (or `null` in `permission` to skip checks):

- `permission.save_and_unlock`
- `permission.ask_to_unblock`

Uses the standard `auth()->user()->can(...)`.

---

## `broadcast` mode (Laravel Echo)

With **`heartbeat`**, the client only learns about lock changes on the next timer tick (e.g. every 10 seconds), plus Livewire request latency. With **`broadcast`**, the server pushes to the record channel — **unlock requests, lock transfers, etc. arrive much sooner** than with heartbeat alone (roughly hundreds of milliseconds vs. the poll interval). Your own lock is still renewed periodically on the client; see `renew_interval_seconds` in config.

### Official documentation

Everything below assumes Laravel’s standard broadcasting stack. Environment variables, driver choice (**Reverb**, **Pusher**, **Ably**, etc.), and server setup are covered here:

**[Laravel — Broadcasting](https://laravel.com/docs/broadcasting)**

Follow that guide until ordinary broadcast events and Echo subscriptions work (including private channel authorization).

### Enabling broadcast for this package (short checklist)

1. **Per Laravel docs**, enable broadcasting: `BROADCAST_CONNECTION`, broker packages if needed, `php artisan install:broadcasting` (or manual `config/broadcasting.php`), run **Reverb** / configure **Pusher**, etc.
2. **Frontend.** Ensure your Vite bundle includes **laravel-echo** and your broker client (e.g. **pusher-js** for Pusher), and that the browser exposes **`window.Echo`** with **`private()`**, as in the Echo client section of the docs.
3. **Package assets.** After `php artisan filament-resource-lock:install`, you get `resources/js/filament-resource-lock/echo.js` and the import in `resources/js/bootstrap.js`. Align `echo.js` with your broker and `.env` keys (host, port, `VITE_*` — see the same Laravel documentation).
4. **Package config.** Set `'update_driver' => 'broadcast'` in `config/filament-resource-lock.php`. Adjust `transports.broadcast` if needed (`channel_prefix`, `event`, `renew_interval_seconds`).

**From the package:** a private channel `{channel_prefix}.{modelHash}.{id}` is registered automatically; by default any authenticated user may subscribe. Tighten authorization in your app (custom channel logic, etc.) if required.

---

## Usage

### 1. Model

Add the lock relation trait:

```php
use Androsamp\FilamentResourceLock\Concerns\HasResourceLocks;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasResourceLocks;
}
```

Adds the `resourceLock()` morph relation. In **`database`** mode it is useful for eager loading; in **`redis`** mode live state comes from cache via `ResourceLockManager`.

### 2. `EditRecord` page

Add the page trait:

```php
use Androsamp\FilamentResourceLock\Concerns\InteractsWithResourceLock;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use InteractsWithResourceLock;

    protected static string $resource = CustomerResource::class;
}
```

Livewire will call `bootInteractsWithResourceLock`, `mountInteractsWithResourceLock`, and related hooks automatically (trait naming convention).

Behavior:

- Opening a record runs **acquire/refresh** (heartbeat).
- If another session holds the lock, the form and save action are **disabled** and a modal appears; actions include “Back to list”, and with permissions “Save and release” / “Request unlock”.
- The lock owner can accept or decline via **Filament Notifications**.

### 3. List table column

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

The icon and tooltip reflect an **active** lock (respecting TTL and `releasing` state after soft-release).

---

## Soft-release route

The package registers a signed GET route `filament-resource-lock.release` (`web`, `signed` middleware). In **broadcast** mode it runs when the tab closes or on SPA navigation to mark the lock as releasing with a **grace period** — handy when the user simply reloads the page.

Ensure `APP_URL` in `.env` matches your application URL or signed URLs may fail validation.

---

## Localization

Strings load from the package (`filament-resource-lock::resource-lock.*`). Publish or override language files as needed (bundled `en` and `ru`).

---

## Developing the package (monorepo / path repository)

If the package is required via `path` in the app’s root `composer.json`, `composer dump-autoload` is usually enough after code changes.

---

## License

MIT.
