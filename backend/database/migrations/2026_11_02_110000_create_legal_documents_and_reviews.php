<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned legal pages edited in the admin (privacy, terms, refunds, shipping) and moderated
 * customer reviews. A published legal version is never edited; a change is a new version, so
 * every consent and order can point at the exact text the customer agreed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                      // LegalDocuments::KINDS
            $table->string('locale', 8)->default('en');
            $table->string('version', 20);                   // Y-m-d, or Y-m-d.2 for a second change on one day
            $table->string('title', 160);
            $table->longText('body');                        // Markdown; raw HTML is stripped when shown
            $table->string('change_note', 500)->nullable();  // what changed, shown in the history
            $table->string('status', 12)->index();           // draft | published | superseded
            $table->date('effective_on')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'locale', 'version']);
            $table->index(['kind', 'locale', 'status']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('order_id')->nullable()->unique();     // one review per order
            $table->foreignId('offering_id')->nullable()->index();
            $table->foreignId('product_id')->nullable()->index();
            $table->string('subject', 160)->nullable();              // what was bought, e.g. "Janma Kundali Reading"
            $table->unsignedTinyInteger('rating');                   // 1–5
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('display_name', 80);
            $table->string('city', 80)->nullable();
            $table->string('locale', 8)->default('en');
            $table->string('status', 12)->index();                   // pending | approved | rejected | hidden
            $table->string('source', 12);                            // customer | staff
            $table->boolean('verified')->default(false);             // linked to a paid order
            $table->boolean('featured')->default(false);
            $table->text('reply')->nullable();                       // public reply from the team
            $table->timestamp('replied_at')->nullable();
            $table->string('reject_reason', 300)->nullable();        // shown to the customer
            $table->string('permission_note', 300)->nullable();      // staff-entered: how permission to publish was given
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('legal_documents');
    }
};
