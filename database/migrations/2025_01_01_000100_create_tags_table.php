<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('drawing_manager_tags')) {
            return;
        }

        Schema::create('drawing_manager_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('drawing_manager_tags')) {
            return;
        }
        Schema::dropIfExists('drawing_manager_tags');
    }
};
