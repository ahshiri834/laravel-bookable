<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->morphs('bookable');
            $table->nullableMorphs('owner');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('total')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bookable_type', 'bookable_id', 'starts_at', 'ends_at'], 'bookings_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
