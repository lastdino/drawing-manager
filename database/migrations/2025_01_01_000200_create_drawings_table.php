<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_manager_drawings')) {
            return;
        }

        Schema::create('drawing_manager_drawings', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 120); // 図番（グローバルユニーク）
            $table->string('title', 255)->nullable();

            // フォルダ
            $table->foreignId('folder_id')->nullable()->constrained('drawing_manager_folders')->nullOnDelete();

            // 最新版ポインタ（Spatie Media Library の medias.id）
            $table->unsignedBigInteger('current_media_id')->nullable()->index();

            $table->timestamps();

            // Use the default index name to avoid global name collisions on SQLite
            // Default will be: drawing_manager_drawings_number_unique
            $table->unique('number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_manager_drawings')) {
            return;
        }
        Schema::dropIfExists('drawing_manager_drawings');
    }
};
