<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_manager_folders')) {
            return; // 既存テーブルへの衝突回避
        }

        Schema::create('drawing_manager_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('drawing_manager_folders')->nullOnDelete();
            $table->timestamps();

            // 同一親配下でのフォルダ名重複を禁止（必要に応じてアプリ側で解除可）
            $table->unique(['parent_id', 'name']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_manager_folders')) {
            return;
        }
        Schema::dropIfExists('drawing_manager_folders');
    }
};
