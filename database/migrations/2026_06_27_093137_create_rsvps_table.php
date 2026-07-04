<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->string('attendance')->default('yes');   // yes | no | maybe
            $table->string('food_option')->nullable();      // e.g. standard | vegetarian
            $table->text('notes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'attendance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};
