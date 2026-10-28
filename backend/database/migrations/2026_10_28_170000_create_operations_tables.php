<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Puja: suppliers (priests/temples) with written authorisation, and bookings ----
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('kind', 12);                    // priest | temple | vendor
            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('authorization_doc', 255)->nullable(); // private disk path
            $table->date('authorized_until')->nullable();
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('offering_supplier', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['offering_id', 'supplier_id']);
        });
        Schema::create('puja_bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id')->unique()->constrained();
            $table->foreignId('offering_id');
            // awaiting_details → requested → scheduled → performed → completed ; cancelled
            $table->string('status', 20)->index();
            $table->string('location', 12)->nullable();     // online | home | temple
            $table->date('preferred_date')->nullable();
            $table->text('sankalp')->nullable();            // encrypted JSON: names, gotra, wishes, address
            $table->foreignId('supplier_id')->nullable();
            $table->dateTime('scheduled_at')->nullable();   // UTC
            $table->string('live_link', 255)->nullable();
            $table->text('customer_note')->nullable();      // shown to customer
            $table->json('evidence')->nullable();           // private file paths
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // ---- Consultation slots (principal practitioner) ----
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday');         // 0 = Sunday
            $table->time('start_local');
            $table->time('end_local');
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->string('modes', 60)->default('audio,video,in_person');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 120)->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id')->nullable()->unique();
            $table->foreignId('variant_id');
            $table->dateTime('start_utc')->index();
            $table->dateTime('end_utc');
            // held → confirmed → completed ; cancelled ; no_show
            $table->string('status', 12)->index();
            $table->timestamp('hold_expires_at')->nullable();
            $table->string('mode', 20)->nullable();
            $table->foreignId('birth_profile_id')->nullable();
            $table->text('topic')->nullable();
            $table->string('meeting_link', 255)->nullable();
            $table->unsignedTinyInteger('reschedules')->default(0);
            $table->timestamps();
        });

        // One row per slot unit a live booking occupies. The UNIQUE key on unit_start makes double
        // booking impossible even under concurrent requests; rows are deleted when a booking is released.
        Schema::create('booking_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->dateTime('unit_start')->unique();
        });

        // ---- Expert-prepared reports (all report offerings except palmistry) ----
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id')->unique()->constrained();
            $table->string('offering_slug', 120);
            // awaiting_details → in_preparation → completed
            $table->string('status', 20)->index();
            $table->json('subjects')->nullable();           // birth details snapshot(s)
            $table->json('chart')->nullable();              // computed chart(s)
            $table->json('sections')->nullable();
            $table->string('language', 8)->default('en');
            $table->text('question')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // ---- Support ----
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->string('category', 20);
            $table->string('subject', 160);
            $table->string('priority', 8)->default('normal');
            $table->string('status', 20)->index();          // open | waiting_customer | resolved | closed
            $table->foreignId('order_id')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->timestamp('last_customer_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id');
            $table->boolean('internal')->default(false);   // staff-only note
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- Privacy requests ----
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->nullable()->index(); // null after anonymisation
            $table->string('type', 10);                     // export | delete
            $table->string('status', 12);                   // completed | blocked
            $table->json('steps')->nullable();
            $table->string('blocked_reason', 255)->nullable();
            $table->timestamps();
        });

        // ---- Rashifal / horoscope publishing ----
        Schema::create('horoscopes', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10);                   // daily | weekly | monthly | yearly
            $table->unsignedTinyInteger('sign');            // 0 = Mesha
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('locale', 8)->default('hi');
            $table->text('body');
            // draft → in_review → published ; rejected
            $table->string('status', 12)->index();
            $table->foreignId('author_id');
            $table->foreignId('reviewer_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['period', 'sign', 'starts_on', 'locale']);
        });
    }

    public function down(): void
    {
        foreach (['horoscopes', 'privacy_requests', 'ticket_messages', 'tickets', 'report_jobs', 'booking_units', 'bookings', 'availability_exceptions', 'availability_rules', 'puja_bookings', 'offering_supplier', 'suppliers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
