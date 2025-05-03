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
        Schema::create('events', function (Blueprint $table) {
            // Use UUID as primary key instead of auto-increment ID
            $table->char('id',36)->primary();
            $table->char('public_workshop_id',36);
            $table->bigInteger('public_numerical_key')->unsigned();
            $table->char('public_key', 9);
            $table->char('private_key', 72);
            $table->char('online_link', 45);
            $table->string('event_name')->nullable()->comment('Name of CPD Programme');
            $table->dateTime('event_start_dT');
            $table->dateTime('event_end_dT')->nullable();
            $table->decimal('cpd_points_earned', 8, 2)->default(1);
            $table->string('event_type')->nullable();
            $table->enum('event_privacy', ['public', 'private', 'restricted'])->default('public');
            $table->json('misc_data')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
