<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Blog / articles (Markdown, optional Hindi), written as drafts and published by a content publisher. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('slug', 160)->unique();
            $table->string('category', 30)->index();         // Blog::CATEGORIES
            $table->string('title', 200);
            $table->string('title_hi', 200)->nullable();
            $table->string('excerpt', 400);
            $table->string('excerpt_hi', 400)->nullable();
            $table->longText('body');                         // Markdown
            $table->longText('body_hi')->nullable();
            $table->json('tags')->nullable();
            $table->json('services')->nullable();             // related offering slugs
            $table->string('cover_path', 255)->nullable();
            $table->string('status', 12)->index();            // draft | published
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('reading_minutes')->default(3);
            $table->foreignId('author_id')->nullable();
            $table->foreignId('published_by')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
