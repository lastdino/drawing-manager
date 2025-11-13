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
- Database tables for: `folders`, `tags`, `drawings`, pivot tables `drawing_tag`, `drawing_role`
- Spatie packages: `spatie/laravel-permission`, `spatie/laravel-medialibrary`
- Livewire 3, Flux UI 2

Note: This package ships its own Eloquent models (`Lastdino\\DrawingManager\\Models\\{DrawingManagerDrawing,DrawingManagerDrawingFolder,DrawingManagerDrawingTag}`) and migrations for greenfield projects. If your application already has equivalent tables/models, you can keep using them; simply do not run this package's migrations.

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
- `media_disk`: Disk used by Spatie Media Library (default `local`)

4) Routes
---------
The package auto-registers routes (names in parentheses):
- GET `/{prefix}` → drawings index (Livewire component) (`drawings.index`)
- GET `/{prefix}/{drawing}/latest/{type}` → download the latest file for type (`pdf`, `dwg`, `dxf`, `step`, `iges`) (`drawings.download.latest`)
- GET `/{prefix}/revisions/{media}` → download a specific media item (`drawings.download.revision`)

Remove conflicting host routes under the same prefix (default `/drawings`) if you had any.

Authorization
-------------
A policy is registered automatically for the package model class only:
- `Lastdino\\DrawingManager\\Models\\DrawingManagerDrawing`

Default logic (`DrawingPolicy`):
- `view`/`download`: allowed if the user has any of the drawing's allowed role names. Admins bypass.
- `create`: allowed for admins; otherwise requires `department_id != null`.
- `update`: allowed for admins or if `user.department_id === drawing.managing_department_id`.

If you use a host app model such as `App\\Models\\Drawing`, register your own policy mapping for it in your application.

Livewire UI
-----------
The UI uses Flux UI components. If you don't see changes, run your frontend builder:
- `npm run dev` or `composer run dev`

You can publish the package views to customize the UI:

```
php artisan vendor:publish --tag=drawing-manager-views --no-interaction
```

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
This package ships migrations for greenfield projects and will auto-load them via the service provider. They are executed when you run `php artisan migrate`.

Tables provided:
- `folders` (self-referencing parent, unique per parent+name)
- `tags` (unique `slug`)
- `drawings` (unique `number`, FKs to `folders` and optionally to host `departments` if you reference it)
- `drawing_tag` (pivot)
- `drawing_role` (pivot to Spatie Permission `roles`)

Notes:
- If you already have equivalent tables in your app, simply do not run these migrations in a fresh environment that already contains your schema.
- Ensure Spatie Permission migrations are run before using this package's `drawing_role` pivot table.

License
-------
MIT
