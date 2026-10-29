<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Shiprocket shipments (courier API) and RazorpayX payouts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // packed parcel, used for courier booking
            $table->unsignedInteger('ship_weight_g')->default(200);
            $table->unsignedSmallInteger('ship_length_cm')->default(12);
            $table->unsignedSmallInteger('ship_breadth_cm')->default(10);
            $table->unsignedSmallInteger('ship_height_cm')->default(6);
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('provider', 16)->nullable();               // shiprocket | null (manual)
            $table->string('provider_order_id', 40)->nullable();
            $table->string('provider_shipment_id', 40)->nullable()->unique();
            $table->string('provider_status', 60)->nullable();
            $table->string('label_url', 255)->nullable();
            $table->string('provider_error', 300)->nullable();
            $table->timestamp('synced_at')->nullable();
        });
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('provider', 16)->nullable();               // razorpayx | null (manual UTR)
            $table->string('provider_payout_id', 40)->nullable()->unique();
            $table->string('provider_status', 20)->nullable();
            $table->string('provider_error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payouts', fn (Blueprint $t) => $t->dropColumn(['provider', 'provider_payout_id', 'provider_status', 'provider_error', 'sent_at', 'sent_by']));
        Schema::table('shipments', fn (Blueprint $t) => $t->dropColumn(['provider', 'provider_order_id', 'provider_shipment_id', 'provider_status', 'label_url', 'provider_error', 'synced_at']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['ship_weight_g', 'ship_length_cm', 'ship_breadth_cm', 'ship_height_cm']));
    }
};
