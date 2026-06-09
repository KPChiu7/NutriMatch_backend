<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------
        // rnd_profiles
        // --------------------------------------------------------
        Schema::create('rnd_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('prc_license_number', 50)->unique();
            $table->date('prc_expiry_date')->nullable();
            $table->string('specialization', 255)->nullable()->comment('Primary clinical specialization.');
            $table->json('language_codes')->nullable()->comment('Array of ISO 639-1 codes: ["tl","ceb","en"].');
            $table->text('bio')->nullable();
            $table->decimal('consultation_fee', 8, 2)->default(0.00);
            $table->boolean('available_for_new_clients')->default(true);
            $table->boolean('is_verified')->default(false)->comment('0=pending; 1=verified by admin.');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('is_verified');
            $table->index('available_for_new_clients');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // rnd_languages
        // --------------------------------------------------------
        Schema::create('rnd_languages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rnd_id')->comment('FK to users.id (role=rnd).');
            $table->string('language_code', 10)->comment('ISO 639-1 code.');
            $table->string('language_name', 50)->comment('Display name.');

            $table->unique(['rnd_id', 'language_code']);
            $table->index('language_code');

            $table->foreign('rnd_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // rnd_availability_schedules
        // --------------------------------------------------------
        Schema::create('rnd_availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rnd_id')->comment('FK to users.id (role=rnd).');
            $table->tinyInteger('day_of_week')->comment('0=Sunday … 6=Saturday.');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->date('effective_from')->default(now());
            $table->date('effective_to')->nullable()->comment('NULL = no end date.');
            $table->timestamp('created_at')->useCurrent();

            $table->index('rnd_id');
            $table->index(['day_of_week', 'is_available']);

            $table->foreign('rnd_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // client_profiles
        // --------------------------------------------------------
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date_of_birth')->nullable();
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->string('language_code', 10)->nullable()->comment('Preferred language: tl, ceb, ilo, en.');
            $table->string('address', 500)->nullable();
            $table->string('emergency_contact', 255)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->timestamps();

            $table->unique('user_id');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // client_health_profiles
        // --------------------------------------------------------
        Schema::create('client_health_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('FK to users.id (role=client).');
            $table->json('medical_conditions')->nullable()->comment('["diabetes","hypertension","ckd"].');
            $table->json('allergies')->nullable()->comment('["peanuts","shellfish","lactose"].');
            $table->json('dietary_restrictions')->nullable()->comment('["vegetarian","halal","kosher"].');
            $table->json('health_goals')->nullable()->comment('["weight_loss","blood_sugar_control"].');
            $table->string('religion', 100)->nullable()->comment('For culturally appropriate meal planning.');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_health_profiles');
        Schema::dropIfExists('client_profiles');
        Schema::dropIfExists('rnd_availability_schedules');
        Schema::dropIfExists('rnd_languages');
        Schema::dropIfExists('rnd_profiles');
    }
};
