<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------
        // rnd_client_relationships — Central FK hub
        // FKs use RESTRICT to prevent destruction of clinical records
        // --------------------------------------------------------
        Schema::create('rnd_client_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rnd_id');
            $table->unsignedBigInteger('client_id');
            $table->enum('status', ['pending', 'active', 'discharged'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rnd_id', 'client_id']);
            $table->index('client_id');
            $table->index('status');

            $table->foreign('rnd_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
            $table->foreign('client_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // appointments
        // --------------------------------------------------------
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->dateTime('scheduled_at');
            $table->enum('type', ['in_person', 'video', 'chat'])->default('video');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->comment('Planned session length in minutes.');
            $table->string('video_session_url', 1000)->nullable()->comment('Client join URL.');
            $table->string('meeting_id', 255)->nullable()->comment('External provider meeting ID.');
            $table->string('cancellation_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('relationship_id');
            $table->index(['scheduled_at', 'status']);

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // consultation_sessions — Video lifecycle tracking
        // --------------------------------------------------------
        Schema::create('consultation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->enum('video_provider', ['zoom', 'daily_co', 'jitsi', 'twilio_video', 'google_meet', 'other'])->default('daily_co');
            $table->string('external_session_id', 255)->nullable();
            $table->string('host_url', 1000)->nullable()->comment('RND host join URL. Never returned to clients.');
            $table->string('participant_url', 1000)->nullable()->comment('Client participant URL.');
            $table->string('recording_url', 1000)->nullable();
            $table->enum('session_status', ['scheduled', 'active', 'ended', 'failed', 'cancelled'])->default('scheduled');
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ended_at')->nullable();
            $table->unsignedSmallInteger('actual_duration_min')->nullable();
            $table->json('provider_metadata')->nullable()->comment('Full provider API response. Never exposed to clients.');
            $table->timestamps();

            $table->index('appointment_id');
            $table->index('session_status');
            $table->index('external_session_id');

            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // pre_consultation_screenings
        // --------------------------------------------------------
        Schema::create('pre_consultation_screenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->decimal('height_cm', 5, 2);
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('bmi_category', 50)->nullable()->comment('WHO Asia-Pacific classification.');
            $table->decimal('bmr_kcal', 7, 2)->nullable();
            $table->decimal('tdee_kcal', 7, 2)->nullable();
            $table->enum('activity_level', ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'])->default('sedentary');
            $table->tinyInteger('nrs_score')->nullable()->comment('NRS-2002 score (0-7).');
            $table->enum('nrs_risk', ['no_risk', 'at_risk', 'high_risk'])->nullable();
            $table->text('symptoms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('client_id');
            $table->index('appointment_id');

            $table->foreign('client_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // ncp_records — All 4 NCP phases per encounter
        // --------------------------------------------------------
        Schema::create('ncp_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->date('encounter_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            // Phase 1: Assessment
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->decimal('blood_glucose', 5, 2)->nullable();
            $table->decimal('hba1c', 4, 2)->nullable();
            $table->text('lab_notes')->nullable();
            $table->text('assessment_notes')->nullable();
            // Phase 2: Diagnosis (PES)
            $table->string('pes_problem', 500)->nullable();
            $table->text('pes_etiology')->nullable();
            $table->text('pes_signs')->nullable();
            // Phase 3: Intervention
            $table->text('diet_prescription')->nullable();
            $table->decimal('target_kcal', 7, 2)->nullable();
            $table->decimal('target_protein_g', 6, 2)->nullable();
            $table->decimal('target_carb_g', 6, 2)->nullable();
            $table->decimal('target_fat_g', 6, 2)->nullable();
            $table->text('intervention_notes')->nullable();
            // Phase 4: Monitoring
            $table->text('monitoring_notes')->nullable();
            $table->enum('goal_status', ['met', 'partially_met', 'not_met', 'ongoing'])->nullable();
            $table->timestamps();

            $table->index('relationship_id');
            $table->index('appointment_id');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // progress_records
        // --------------------------------------------------------
        Schema::create('progress_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->date('record_date');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->decimal('blood_glucose', 5, 2)->nullable();
            $table->decimal('hba1c', 4, 2)->nullable();
            $table->unsignedTinyInteger('adherence_pct')->nullable()->comment('0-100 dietary adherence estimate.');
            $table->text('client_notes')->nullable();
            $table->text('rnd_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('relationship_id');
            $table->index('record_date');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_records');
        Schema::dropIfExists('ncp_records');
        Schema::dropIfExists('pre_consultation_screenings');
        Schema::dropIfExists('consultation_sessions');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('rnd_client_relationships');
    }
};
