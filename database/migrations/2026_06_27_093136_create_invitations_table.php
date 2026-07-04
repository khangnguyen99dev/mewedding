<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('status')->default('draft');     // draft | published
            $table->timestamp('published_at')->nullable();
            $table->string('password')->nullable();         // optional private gate
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('locale', 8)->default('vi');

            // Schema-driven, per-template content. JSON is intentional: the editable
            // shape varies per template, so it cannot be modelled as fixed columns.
            $table->json('settings')->nullable();           // section -> fields (+ repeaters)
            $table->json('theme')->nullable();              // color/font overrides
            $table->json('seo')->nullable();                // title/description/og_image

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
