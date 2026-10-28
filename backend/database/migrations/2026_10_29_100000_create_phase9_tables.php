<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 9: push + in-app notifications, AI assistant, team practitioners, commission and payouts. */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Notifications ----
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('token', 512);
            $table->char('token_hash', 64)->unique();      // sha256(token) for lookups
            $table->string('platform', 10)->default('android');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('kind', 20);                     // booking|report|puja|palm|ticket|order|general
            $table->string('ref', 26)->nullable();
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('link', 255)->nullable();
            $table->json('channels')->nullable();          // {"email":"sent","push":"sent:2"} …
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_1h_at')->nullable();
        });

        // ---- AI assistant ----
        Schema::create('assistant_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('channel', 8);                   // web | app
            $table->string('ip_hash', 64)->nullable();     // guests: daily limit without storing the IP
            $table->boolean('handoff')->default(false);
            $table->timestamps();
        });
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 10);                     // user | assistant
            $table->text('content');
            $table->string('provider', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('flag', 20)->nullable();         // crisis | handoff | blocked
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- Team practitioners ----
        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->unique();         // staff user with the practitioner role
            $table->string('display_name', 120);
            $table->text('bio')->nullable();
            $table->string('languages', 160)->nullable();   // comma separated
            $table->string('specialties', 255)->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->string('photo_path', 255)->nullable();  // uploads disk
            $table->string('tier', 10)->default('team');    // team | premium | elite
            // draft → submitted → approved ; suspended
            $table->string('status', 12)->default('draft')->index();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('payout_details')->nullable();     // encrypted JSON: method, upi / account
            $table->timestamps();
        });
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('share_bps');      // practitioner share, basis points of the price
            $table->date('effective_from');
            $table->string('status', 10)->index();          // proposed | approved | rejected
            $table->foreignId('proposed_by');
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('practitioner_id')->constrained();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 10)->index();          // proposed → approved → paid ; cancelled
            $table->text('method_snapshot')->nullable();    // encrypted payout details at proposal time
            $table->foreignId('proposed_by');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('utr', 40)->nullable();          // bank reference of the transfer
            $table->timestamps();
        });
        Schema::create('practitioner_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained();
            $table->foreignId('booking_id')->unique();
            $table->foreignId('order_id');
            $table->unsignedBigInteger('gross_minor');
            $table->unsignedSmallInteger('share_bps');
            $table->unsignedBigInteger('amount_minor');
            // pending (refund window) → available → in_payout → paid ; reversed
            $table->string('status', 10)->index();
            $table->timestamp('available_at');
            $table->foreignId('payout_id')->nullable()->index();
            $table->timestamps();
        });

        // Availability and slot locks per resource: 0 = Acharya Ji, otherwise practitioners.id
        Schema::table('availability_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('resource_key')->default(0)->index();
        });
        Schema::table('availability_exceptions', function (Blueprint $table) {
            $table->unsignedBigInteger('resource_key')->default(0);
            $table->dropUnique(['date']);
            $table->unique(['resource_key', 'date']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('practitioner_id')->nullable()->index();
        });
        Schema::table('booking_units', function (Blueprint $table) {
            $table->unsignedBigInteger('resource_key')->default(0);
            $table->dropUnique(['unit_start']);
            $table->unique(['resource_key', 'unit_start']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_units', function (Blueprint $table) {
            $table->dropUnique(['resource_key', 'unit_start']);
            $table->unique('unit_start');
            $table->dropColumn('resource_key');
        });
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['practitioner_id', 'reminded_24h_at', 'reminded_1h_at']));
        Schema::table('availability_exceptions', function (Blueprint $table) {
            $table->dropUnique(['resource_key', 'date']);
            $table->unique('date');
            $table->dropColumn('resource_key');
        });
        Schema::table('availability_rules', fn (Blueprint $t) => $t->dropColumn('resource_key'));
        foreach (['practitioner_earnings', 'payouts', 'commission_rules', 'practitioners', 'assistant_messages', 'assistant_conversations', 'assistant_knowledge', 'user_notifications', 'push_tokens'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
