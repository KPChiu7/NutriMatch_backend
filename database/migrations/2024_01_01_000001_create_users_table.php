<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['admin', 'rnd', 'client']);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('password', 255)->comment('bcrypt-hashed. Never store plain text.');
            $table->string('phone', 20)->nullable();
            $table->string('profile_photo', 500)->nullable();
            $table->boolean('is_active')->default(true)->comment('0 = deactivated; records retained.');
            $table->timestamp('email_verified_at')->nullable()->comment('NULL = unverified.');
            $table->softDeletes()->comment('Soft delete. NULL = not deleted.');
            $table->timestamps();

            $table->index('role');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
