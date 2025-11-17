<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_manager_drawing_tag')) {
            return;
        }

        Schema::create('drawing_manager_drawing_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('drawing_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('drawing_id')->references('id')->on('drawing_manager_drawings')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('drawing_manager_tags')->cascadeOnDelete();

            $table->primary(['drawing_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_manager_drawing_tag')) {
            return;
        }
        Schema::dropIfExists('drawing_manager_drawing_tag');
    }
};
