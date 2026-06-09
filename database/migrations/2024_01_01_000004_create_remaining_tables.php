<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------
        // food_exchange_categories
        // --------------------------------------------------------
        Schema::create('food_exchange_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('kcal_per_exchange', 6, 2)->default(0.00);
            $table->decimal('carbs_g', 5, 2)->nullable();
            $table->decimal('protein_g', 5, 2)->nullable();
            $table->decimal('fat_g', 5, 2)->nullable();
            $table->string('color', 7)->nullable();
            $table->tinyInteger('sort_order')->default(0);
        });

        // --------------------------------------------------------
        // food_exchange_items — 550 FEL items (seeded separately)
        // --------------------------------------------------------
        Schema::create('food_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name', 255);
            $table->string('local_name', 255)->nullable();
            $table->string('subcategory', 50)->nullable();
            $table->decimal('ep_grams', 7, 2)->nullable()->comment('Edible portion in grams.');
            $table->string('household_measure', 100)->nullable();
            $table->boolean('is_high_sodium')->default(false);
            $table->boolean('is_high_potassium')->default(false);
            $table->boolean('is_high_phosphorus')->default(false);
            $table->boolean('is_high_fiber')->default(false);
            $table->boolean('is_low_gi')->default(false);
            $table->boolean('ok_for_diabetes')->default(true);
            $table->boolean('ok_for_hypertension')->default(true);
            $table->boolean('ok_for_renal')->default(true);
            $table->boolean('is_free_food')->default(false)->comment('True if <16 kcal per serving.');
            $table->string('notes', 500)->nullable();

            $table->index('category_id');
            $table->fullText(['name', 'local_name']);

            $table->foreign('category_id')
                  ->references('id')->on('food_exchange_categories')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // meal_plans
        // --------------------------------------------------------
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->string('name', 255);
            $table->enum('condition', ['diabetes', 'hypertension', 'renal', 'weight_loss', 'weight_gain', 'general'])->default('general');
            $table->decimal('target_kcal', 7, 2)->nullable();
            $table->decimal('total_vegetable', 4, 1)->default(0.0);
            $table->decimal('total_fruit', 4, 1)->default(0.0);
            $table->decimal('total_milk', 4, 1)->default(0.0);
            $table->decimal('total_rice', 4, 1)->default(0.0);
            $table->decimal('total_meat', 4, 1)->default(0.0);
            $table->decimal('total_fat', 4, 1)->default(0.0);
            $table->decimal('total_sugar', 4, 1)->default(0.0);
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->index('relationship_id');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // meal_plan_meals
        // --------------------------------------------------------
        Schema::create('meal_plan_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_plan_id');
            $table->enum('meal_time', ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner', 'bedtime_snack']);
            $table->decimal('vegetable_exchanges', 4, 1)->default(0.0);
            $table->decimal('fruit_exchanges', 4, 1)->default(0.0);
            $table->decimal('milk_exchanges', 4, 1)->default(0.0);
            $table->decimal('rice_exchanges', 4, 1)->default(0.0);
            $table->decimal('meat_exchanges', 4, 1)->default(0.0);
            $table->decimal('fat_exchanges', 4, 1)->default(0.0);
            $table->decimal('sugar_exchanges', 4, 1)->default(0.0);
            $table->text('meal_notes')->nullable();

            $table->unique(['meal_plan_id', 'meal_time']);

            $table->foreign('meal_plan_id')
                  ->references('id')->on('meal_plans')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // meal_plan_food_items
        // --------------------------------------------------------
        Schema::create('meal_plan_food_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_plan_meal_id');
            $table->unsignedBigInteger('food_item_id')->nullable()->comment('FK to FEL. NULL for API-sourced.');
            $table->string('food_name', 255)->comment('Display name. Only field persisted for external items.');
            $table->enum('source_type', ['fel', 'fnri_fct', 'usda', 'custom'])->default('fel');
            $table->string('external_food_id', 100)->nullable()->comment('USDA fdcId or FNRI food code.');
            $table->decimal('exchanges', 4, 1)->default(1.0);
            $table->string('household_measure', 100)->nullable();
            $table->string('notes', 500)->nullable();

            $table->index('meal_plan_meal_id');
            $table->index('food_item_id');
            $table->index('source_type');

            $table->foreign('meal_plan_meal_id')
                  ->references('id')->on('meal_plan_meals')
                  ->onDelete('cascade');
            $table->foreign('food_item_id')
                  ->references('id')->on('food_exchange_items')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // messages
        // --------------------------------------------------------
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->enum('message_type', ['text', 'file', 'image', 'system'])->default('text');
            $table->string('attachment_url', 1000)->nullable();
            $table->string('attachment_type', 50)->nullable()->comment('MIME type.');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamp('created_at')->useCurrent();

            $table->index('relationship_id');
            $table->index('sender_id');
            $table->index(['relationship_id', 'is_read']);
            $table->index('deleted_at');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
            $table->foreign('sender_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // resources
        // --------------------------------------------------------
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->enum('type', ['article', 'pdf', 'video', 'link'])->default('article');
            $table->string('file_path', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('uploaded_by');
            $table->index('is_active');

            $table->foreign('uploaded_by')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // reminders
        // --------------------------------------------------------
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->enum('type', ['appointment', 'meal_log', 'medication', 'general'])->default('general');
            $table->dateTime('send_at');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('client_id');
            $table->index(['is_sent', 'send_at']); // Optimizes scheduler query

            $table->foreign('client_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // notification_logs
        // --------------------------------------------------------
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type', 100)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->unsignedBigInteger('recipient_id');
            $table->enum('channel', ['email', 'sms', 'push', 'in_app']);
            $table->string('subject', 255)->nullable();
            $table->text('content')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'bounced'])->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('recipient_id');
            $table->index(['status', 'created_at']);
            $table->index(['notifiable_type', 'notifiable_id']);

            $table->foreign('recipient_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // invoices
        // --------------------------------------------------------
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_pct', 5, 2)->default(10.00)->comment('Frozen at creation.');
            $table->decimal('commission_amt', 10, 2)->default(0.00)->comment('Frozen at creation.');
            $table->enum('status', ['unpaid', 'paid', 'cancelled', 'refunded'])->default('unpaid');
            $table->enum('payment_gateway', ['paymongo', 'stripe', 'xendit', 'paypal', 'cash', 'manual'])->nullable();
            $table->enum('payment_method', ['card', 'gcash', 'maya', 'bank_transfer', 'cash', 'other'])->nullable();
            $table->string('gateway_reference_id', 255)->nullable()->comment('Provider transaction ID.');
            $table->string('payment_url', 1000)->nullable()->comment('Hosted checkout URL. Never logged or exposed freely.');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('relationship_id');
            $table->index('appointment_id');
            $table->index('status');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // payment_transactions
        // --------------------------------------------------------
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->enum('payment_gateway', ['paymongo', 'stripe', 'xendit', 'paypal', 'cash', 'manual']);
            $table->enum('payment_method', ['card', 'gcash', 'maya', 'bank_transfer', 'cash', 'other'])->nullable();
            $table->enum('transaction_type', ['charge', 'refund', 'chargeback', 'adjustment'])->default('charge');
            $table->string('gateway_txn_id', 255)->comment('e.g. PayMongo pay_xxx.');
            $table->json('gateway_response')->nullable()->comment('Full webhook payload for audit.');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded', 'disputed'])->default('pending');
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_reason', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('invoice_id');
            $table->index('gateway_txn_id');
            $table->index('status');

            $table->foreign('invoice_id')
                  ->references('id')->on('invoices')
                  ->onDelete('restrict');
        });

        // --------------------------------------------------------
        // reviews
        // --------------------------------------------------------
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('relationship_id');
            $table->unsignedBigInteger('appointment_id')->unique()->comment('One review per appointment.');
            $table->unsignedTinyInteger('rating')->comment('1-5 stars.');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('relationship_id');

            $table->foreign('relationship_id')
                  ->references('id')->on('rnd_client_relationships')
                  ->onDelete('restrict');
            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->onDelete('cascade');
        });

        // --------------------------------------------------------
        // audit_logs — Immutable. Rows are never updated or deleted.
        // --------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('NULL for system events.');
            $table->string('action', 100)->comment('Machine-readable: user.login, ncp.finalized.');
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable()->comment('IPv4 or IPv6.');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['action', 'created_at']);

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // api_cache — USDA/FNRI FCT response cache
        // --------------------------------------------------------
        Schema::create('api_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique()->comment('MD5 hash of source + params.');
            $table->enum('source_api', ['usda', 'fnri_fct']);
            $table->string('query_term', 255)->nullable();
            $table->json('response');
            $table->timestamp('expires_at');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();

            $table->index('expires_at');
            $table->index('source_api');
        });

        // --------------------------------------------------------
        // system_settings — Key-value config store
        // --------------------------------------------------------
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('Dot-notated: billing.commission_pct.');
            $table->text('value');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('updated_by');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });

        // --------------------------------------------------------
        // sessions — Laravel session store
        // --------------------------------------------------------
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('api_cache');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('meal_plan_food_items');
        Schema::dropIfExists('meal_plan_meals');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('food_exchange_items');
        Schema::dropIfExists('food_exchange_categories');
    }
};
