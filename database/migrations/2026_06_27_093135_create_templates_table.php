<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // folder name, e.g. "nobel"
            $table->string('name');
            $table->string('version')->default('1.0.0');
            $table->string('thumbnail')->nullable();      // public path to preview image
            $table->text('description')->nullable();
            $table->string('status')->default('active');  // active | disabled
            $table->json('manifest')->nullable();         // cached template.json (schema + sections)
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
