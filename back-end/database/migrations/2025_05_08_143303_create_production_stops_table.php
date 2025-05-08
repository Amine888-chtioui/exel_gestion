<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('production_stops', function (Blueprint $table) {
            $table->id();
            $table->string('machine'); // ALPHA 63, ALPHA 19, etc.
            $table->string('machine_group')->nullable(); // For grouping similar machines
            $table->string('ws_key')->nullable(); // Workshop key
            $table->float('stop_time'); // Time in hours
            $table->string('wo_key')->nullable(); // Work order key
            $table->text('wo_name')->nullable(); // Work order description
            $table->string('code1')->nullable(); // Main category (1 Mechanical, 2 Electrical)
            $table->string('code2')->nullable(); // Issue type
            $table->string('code3')->nullable(); // Component
            $table->date('date'); // Date of the stop
            $table->string('komax_model')->nullable(); // Model of the machine
            $table->boolean('is_completed')->default(true); // Whether the stop is resolved
            $table->timestamps();
            
            // Add indexes for frequently queried columns
            $table->index('machine');
            $table->index('date');
            $table->index('code1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_stops');
    }
};