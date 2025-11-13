<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_role')) {
            return;
        }

        Schema::create('drawing_manager_drawing_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('drawing_id');
            $table->unsignedBigInteger('role_id'); // Spatie Permission roles.id

            $table->foreign('drawing_id')->references('id')->on('drawings')->cascadeOnDelete();

            // roles テーブルが存在しない環境でも migrate できるよう、FK は条件付きで別途追加しても良いが
            // ここでは存在を前提とし、無い場合はアプリ側で先に Spatie Permission を migrate してください。
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->primary(['drawing_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_manager_drawing_role')) {
            return;
        }
        Schema::dropIfExists('drawing_manager_drawing_role');
    }
};
