<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rates', function (Blueprint $table) {
            $table->id();
            $table->morphs('bookable');
            $table->unsignedBigInteger('price');          // minor units, per unit per night
            $table->date('starts_on')->nullable();        // inclusive; null = no lower bound
            $table->date('ends_on')->nullable();          // inclusive; null = no upper bound
            $table->json('weekdays')->nullable();         // 0 = Sunday ... 6 = Saturday; null = every day
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rates');
    }
};
