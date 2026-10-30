<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-managed showcase: the About profile (education, positions, awards, research, books,
 * media, expertise) and a public photo/video gallery (pujas, awards, events, media, office).
 * Biography text and stats live in settings (profile.*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 20)->index();         // Profile::SECTIONS
            $table->string('title', 300);
            $table->string('title_hi', 300)->nullable();
            $table->text('detail')->nullable();
            $table->text('detail_hi')->nullable();
            $table->string('year', 40)->nullable();          // "2014", "2011 – present"
            $table->string('group', 20)->nullable();         // awards: international | national
            $table->string('image_path', 255)->nullable();   // award photo (public)
            $table->string('link_url', 255)->nullable();
            $table->boolean('featured')->default(false);     // shown on the home page
            $table->boolean('published')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['section', 'published', 'sort']);
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('title_hi', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('description_hi')->nullable();
            $table->string('category', 20)->index();         // Gallery::CATEGORIES
            $table->date('event_date')->nullable();
            $table->string('location', 160)->nullable();
            $table->foreignId('cover_item_id')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->string('kind', 10);                      // photo | video
            $table->string('path', 255)->nullable();         // photo, under public/
            $table->string('thumb_path', 255)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('video_id', 20)->nullable();      // YouTube video id
            $table->string('caption', 300)->nullable();
            $table->string('caption_hi', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['album_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('profile_entries');
    }
};
