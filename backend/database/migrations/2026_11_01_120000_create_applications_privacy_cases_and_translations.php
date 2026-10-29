<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Astrologer applications from customers (AN-12), the staff privacy queue (PV-03/04) and
 * translations for family and variant names (RP-24, other languages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->string('name', 120);
            $table->string('email', 190);                      // used for the staff (astrologer) account
            $table->string('phone_e164', 20);
            $table->string('city', 100)->nullable();
            $table->unsignedTinyInteger('experience_years');
            $table->string('languages', 160);
            $table->string('specialties', 255);
            $table->text('about');
            $table->string('status', 12)->index();             // submitted → accepted (invited) | rejected | withdrawn
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::table('privacy_requests', function (Blueprint $table) {
            // staff queue: open → in_progress → closed (completed requests are closed at once)
            $table->string('case_status', 12)->default('closed')->index();
            $table->foreignId('assigned_to')->nullable();
            $table->text('notes')->nullable();                 // JSON list of {at, by, text}
            $table->timestamp('closed_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('privacy_hold_reason', 255)->nullable();  // legal hold: blocks self-service deletion
        });

        Schema::table('service_families', fn (Blueprint $t) => $t->json('translations')->nullable());   // {"hi": {"name", "summary"}}
        Schema::table('offering_variants', fn (Blueprint $t) => $t->json('translations')->nullable());  // {"hi": {"name"}}
    }

    public function down(): void
    {
        Schema::table('offering_variants', fn (Blueprint $t) => $t->dropColumn('translations'));
        Schema::table('service_families', fn (Blueprint $t) => $t->dropColumn('translations'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('privacy_hold_reason'));
        Schema::table('privacy_requests', fn (Blueprint $t) => $t->dropColumn(['case_status', 'assigned_to', 'notes', 'closed_at']));
        Schema::dropIfExists('practitioner_applications');
    }
};
