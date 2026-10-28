<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_families', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();          // consultation | report | bundle | puja | chadhava | written_question | vastu | muhurat
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->text('summary')->nullable();
            $table->boolean('requires_supplier')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('family_id')->constrained('service_families');
            $table->string('slug', 120)->unique();
            $table->string('status', 12)->default('draft')->index(); // draft | active | paused | retired
            $table->boolean('featured')->default(false);
            $table->string('image_path', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('offering_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 160);
            $table->string('summary', 300);
            $table->text('body')->nullable();              // plain text paragraphs
            $table->json('includes')->nullable();          // list of strings
            $table->json('audience')->nullable();          // "who should consult"
            $table->text('exclusions')->nullable();
            $table->text('disclaimer')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->unique(['offering_id', 'locale']);
        });

        Schema::create('offering_variants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('mode', 20)->nullable();        // chat | audio | video | in_person | written | report | puja
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->unsignedTinyInteger('participants')->default(1);
            $table->boolean('urgent')->default(false);
            $table->unsignedBigInteger('price_minor')->nullable(); // paise; null = not priced yet
            $table->char('currency', 3)->default('INR');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_offering_id')->constrained('offerings')->cascadeOnDelete();
            $table->foreignId('item_offering_id')->constrained('offerings');
            $table->unique(['bundle_offering_id', 'item_offering_id']);
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('offering_id')->nullable();
            $table->string('name', 120);
            $table->string('phone_e164', 20);
            $table->string('email', 190)->nullable();
            $table->string('mode', 20)->nullable();
            $table->string('language', 20)->nullable();
            $table->date('preferred_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->time('birth_time')->nullable();
            $table->string('birth_place', 200)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 15)->default('new')->index(); // new | contacted | closed | spam
            $table->text('staff_note')->nullable();
            $table->foreignId('handled_by')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8)->default('en');
            $table->string('question', 300);
            $table->text('answer');
            $table->boolean('published')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['faqs', 'enquiries', 'bundle_items', 'offering_variants', 'offering_translations', 'offerings', 'service_families'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
