Lastdino Drawing Manager
=======================

Drawing management (folders, tags, revisions) for Laravel 12 with Livewire 3 and Flux UI.

Features
--------
- Folder tree with lazy loading and virtualization
- Drawings listing with search and tag filters (OR/AND)
- Detail flyout with latest revision indicator
- Create / Edit drawings (number, title, folder, managing department, allowed roles, tags)
- Upload revisions (PDF creates a new revision; CAD attaches to an existing PDF revision)
- Secure downloads via policies (PDF and CAD types)

Requirements
------------
- PHP >= 8.4
- Laravel ^12.0
- Database tables for: `folders`, `drawings`, `tags`, pivot tables `drawing_tag`, `drawing_role`
- Spatie packages: `spatie/laravel-permission`, `spatie/laravel-medialibrary`
- Livewire 3, Flux UI 2

Note: This package ships models (`Lastdino\\DrawingManager\\Models\\{Folder,Drawing,Tag}`) that map to existing host tables. For greenfield projects, you can publish/run the provided migrations when they are added in a future release. For existing apps, continue using your tables — no changes are required.

Installation
------------
1) Install the package

```
composer require lastdino/drawing-manager
```

If developing in a monorepo, add PSR-4 to your root `composer.json`:

```
{
  "autoload": {
    "psr-4": {
      "Lastdino\\\\DrawingManager\\\\": "packages/lastdino/drawing-manager/src/"
    }
  }
}
```

Then run `composer dump-autoload`.

2) Install required peer packages if not present

```
composer require livewire/livewire:^3.6 livewire/flux:^2.6 spatie/laravel-permission:^6.10 spatie/laravel-medialibrary:^11.12
```

3) Configure (optional)

```
php artisan vendor:publish --tag=drawing-manager-config --no-interaction
```

Config `config/drawing-manager.php`:
- `route_prefix`: UI mount path (default `drawings`)
- `middleware`: UI middleware (default `['web','auth']`)
- `authorize_download`: Check policy before streaming files (default `true`)
- `media_disk`: Disk used by Spatie Media Library (default `public`)

4) Routes
---------
The package auto-registers routes:
- GET `/{prefix}` → drawings index (Livewire component)
- GET `/{prefix}/{drawing}/latest/{type}` → latest download for type (`pdf`, `dwg`, `dxf`, `step`, `iges`)
- GET `/{prefix}/revisions/{media}` → specific media download

Remove conflicting host routes for `/drawings` if you had any — delegation is complete.

Authorization
-------------
Policies are registered automatically for both model classes:
- `App\\Models\\Drawing`
- `Lastdino\\DrawingManager\\Models\\Drawing`

Default logic (`DrawingPolicy`):
- `view`/`download`: allowed if the user has any of the drawing's allowed role names. Admins bypass.
- `create`: allowed for admins; otherwise requires `department_id != null`.
- `update`: allowed for admins or if `user.department_id === drawing.managing_department_id`.

You may override by publishing your own policy and registering it in your app.

Livewire UI
-----------
The UI uses Flux UI components. If you don't see changes, run your frontend builder:
- `npm run dev` or `composer run dev`

Testing
-------
Use Pest or PHPUnit to write feature tests. Example:

```
use Lastdino\\DrawingManager\\Livewire\\Drawings\\Index;

it('shows drawings page', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/drawings')->assertSeeLivewire(Index::class);
});
```

Roadmap
-------
- Publishable migrations for greenfield projects
- Download activity logs
- Thumbnails for PDFs
- S3 and external storage examples

Migrations
----------
This package now ships guarded, optional migrations for greenfield projects.

- By default, migrations are NOT auto-loaded to avoid conflicts with existing tables.
- To enable them (e.g., on a fresh project):

```
php artisan vendor:publish --tag=drawing-manager-config --no-interaction
# then set in config/drawing-manager.php
'load_migrations' => true,

php artisan migrate --no-interaction
```

Tables created when enabled:
- `folders` (self-referencing parent, unique per parent folder name)
- `tags` (unique `slug`)
- `drawings` (unique `number`, FKs to `folders` and optionally to host `departments`)
- `drawing_tag` (pivot)
- `drawing_role` (pivot to Spatie Permission `roles`)

Notes:
- If you already have these tables, keep `load_migrations` as `false`.
- Ensure Spatie Permission migrations are run before enabling this package's `drawing_role` pivot.

License
-------
MIT
