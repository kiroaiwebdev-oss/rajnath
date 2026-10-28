<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Gem & remedy store ----
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('slug', 140)->unique();
            $table->string('category', 30)->index();           // gemstone | rudraksha | yantra | other
            $table->string('name', 160);
            $table->string('summary', 300);
            $table->text('description')->nullable();
            $table->string('weight', 60)->nullable();          // e.g. "5.25 carat (5.8 ratti)"
            $table->string('origin', 120)->nullable();
            $table->string('certification', 200)->nullable(); // lab + certificate number, as supplied
            $table->unsignedBigInteger('price_minor');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedTinyInteger('max_per_order')->default(1);
            $table->boolean('active')->default(false)->index();
            $table->string('image_path', 255)->nullable();     // public, re-encoded (no EXIF)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('order_id')->unique()->constrained();
            $table->unsignedInteger('quantity');
            $table->string('status', 12);                      // held | committed | released
            $table->timestamps();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained();
            $table->text('address');                           // encrypted JSON
            $table->string('status', 20)->index();             // awaiting_dispatch | dispatched | delivered | returned | cancelled
            $table->string('courier', 80)->nullable();
            $table->string('awb', 80)->nullable();
            $table->string('tracking_url', 255)->nullable();
            $table->json('events')->nullable();
            $table->timestamps();
        });

        // ---- Palmistry photo report (expert-prepared) ----
        Schema::create('palm_readings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id')->unique()->constrained();
            // awaiting_photos → submitted → in_review → (needs_new_photos → submitted) → completed
            $table->string('status', 20)->index();
            $table->string('dominant_hand', 6)->nullable();    // left | right
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 10)->nullable();
            $table->text('question')->nullable();
            $table->string('left_photo', 255)->nullable();     // private disk paths
            $table->string('right_photo', 255)->nullable();
            $table->text('staff_note')->nullable();            // shown to the customer when new photos are needed
            $table->foreignId('assigned_to')->nullable();
            $table->json('report')->nullable();                // expert-written sections
            $table->string('report_language', 8)->default('en');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('photos_purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['palm_readings', 'shipments', 'stock_reservations', 'products'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
