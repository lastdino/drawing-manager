<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DrawingManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/drawing-manager.php', 'drawing-manager');
    }

    public function boot(): void
    {
        // Routes, views, migrations
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'drawing-manager');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Policies: support both package model
        if (class_exists(\Lastdino\DrawingManager\Models\DrawingManagerDrawing::class)) {
            Gate::policy(\Lastdino\DrawingManager\Models\DrawingManagerDrawing::class, \Lastdino\DrawingManager\Policies\DrawingPolicy::class);
        }

        // Publish config and views for customization
        $this->publishes([
            __DIR__ . '/../config/drawing-manager.php' => config_path('drawing-manager.php'),
        ], 'drawing-manager-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/drawing-manager'),
        ], 'drawing-manager-views');
    }
}
