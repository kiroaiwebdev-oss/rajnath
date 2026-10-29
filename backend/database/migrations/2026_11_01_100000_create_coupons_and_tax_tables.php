<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons (FI-13) and GST tax invoices / credit notes (FI-08).
 * Orders keep subtotal_minor = the amount actually charged (after any coupon), so refunds,
 * earnings and receipts keep working; list_minor and discount_minor record the price before
 * the coupon, tax_minor the GST included in subtotal_minor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();               // stored upper-case
            $table->string('description', 200)->nullable();
            $table->string('type', 8);                          // percent | flat
            $table->unsignedInteger('value');                   // percent 1–100, or paise
            $table->unsignedBigInteger('max_discount_minor')->nullable();
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->json('applies_to')->nullable();             // family codes and/or "product"; null = everything except recharges
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable(); // total uses
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained();
            $table->foreignId('order_id')->unique()->constrained();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('discount_minor');
            $table->timestamp('created_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('list_minor')->nullable()->after('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0)->after('list_minor');
            $table->foreignId('coupon_id')->nullable()->after('discount_minor');
            $table->unsignedBigInteger('tax_minor')->default(0)->after('coupon_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('type', 12);                         // invoice | credit_note
            $table->string('number', 40)->unique();
            $table->string('series', 24);
            $table->foreignId('order_id')->index()->constrained();
            $table->foreignId('refund_id')->nullable()->unique();
            $table->foreignId('invoice_id')->nullable();        // credit note → original invoice
            $table->timestamp('issued_at');
            $table->json('supplier');
            $table->json('buyer');
            $table->string('place_of_supply', 60);
            $table->json('lines');
            $table->unsignedSmallInteger('rate_bps');
            $table->unsignedBigInteger('taxable_minor');
            $table->unsignedBigInteger('cgst_minor')->default(0);
            $table->unsignedBigInteger('sgst_minor')->default(0);
            $table->unsignedBigInteger('igst_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
        });
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('series', 24)->primary();            // e.g. INV-2026-27
            $table->unsignedInteger('last');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('invoices');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['list_minor', 'discount_minor', 'coupon_id', 'tax_minor']));
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
