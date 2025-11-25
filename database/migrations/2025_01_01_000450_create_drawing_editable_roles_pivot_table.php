<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_editable_roles')) {
            return;
        }

        Schema::create('drawing_editable_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('drawing_id');
            $table->unsignedBigInteger('role_id'); // Spatie Permission roles.id

            $table->foreign('drawing_id')
                ->references('id')->on('drawing_manager_drawings')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')->on('roles')
                ->cascadeOnDelete();

            $table->primary(['drawing_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_editable_roles')) {
            return;
        }
        Schema::dropIfExists('drawing_editable_roles');
    }
};
