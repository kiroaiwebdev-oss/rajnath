<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: double-entry ledger, wallet, recharge packages, orders, Razorpay payments,
 * webhook events, refunds and disputes. All money is BIGINT paise. Ledger tables are
 * append-only: application code never updates or deletes entries (only the cached
 * balance on ledger_accounts, which `arj:ledger-verify` recomputes and compares).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();           // e.g. "customer_cash:42", "gateway_clearing"
            $table->string('kind', 40)->index();
            $table->string('normal', 6);                     // debit | credit
            $table->foreignId('user_id')->nullable()->index();
            $table->char('currency', 3)->default('INR');
            $table->bigInteger('balance_minor')->default(0);  // in the account's normal direction
            $table->boolean('allow_negative')->default(false);
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('kind', 40)->index();             // recharge | bonus | reserve | consume | release | refund_* | dispute_*
            $table->string('idempotency_key', 160)->unique();
            $table->string('memo', 255)->nullable();
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_ref', 64)->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->index(['subject_type', 'subject_ref']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions');
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->string('direction', 6);                  // debit | credit
            $table->unsignedBigInteger('amount_minor');      // always > 0
            $table->bigInteger('balance_after_minor');        // account balance after this entry
            $table->timestamp('created_at', 6);
            $table->index(['account_id', 'id']);
        });

        Schema::create('recharge_packages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('name', 80);
            $table->unsignedBigInteger('amount_minor');      // what the customer pays
            $table->unsignedBigInteger('bonus_minor')->default(0); // promotional, non-refundable credit
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->string('status', 20)->index();           // pending_payment | paid | fulfilling | completed | cancelled | refunded
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('INR');
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('reservation_id')->nullable();
            $table->json('policy_versions')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable();
            $table->foreignId('variant_id')->nullable();
            $table->json('offering_snapshot');               // immutable copy of title, scope, disclaimer
            $table->json('variant_snapshot');                // immutable copy of mode, duration, price
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('fulfilment_status', 20)->default('pending');
        });

        // Wallet holds. Records the cash/bonus split so release and consumption are exact.
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('cash_minor');
            $table->unsignedBigInteger('bonus_minor');
            $table->unsignedBigInteger('consumed_minor')->default(0);
            $table->string('status', 12)->index();           // held | consumed | released | partial
            $table->string('subject_type', 60);
            $table->string('subject_ref', 64);
            $table->timestamps();
            $table->index(['subject_type', 'subject_ref']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->string('purpose', 20);                   // wallet_recharge | order
            $table->foreignId('order_id')->nullable()->index();
            $table->foreignId('recharge_package_id')->nullable();
            $table->json('snapshot');                        // package/order snapshot at creation
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('bonus_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3)->default('INR');
            $table->string('provider', 20)->default('razorpay');
            $table->string('provider_mode', 6);              // test | live
            $table->string('provider_order_id', 64)->nullable()->unique();
            $table->string('provider_payment_id', 64)->nullable()->unique();
            $table->string('status', 20)->index();           // created | pending | captured | failed | cancelled | ambiguous
            $table->string('status_reason', 255)->nullable();
            $table->string('method', 30)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('credit_transaction_id')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('check_attempts')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('provider_event_id', 100);
            $table->string('type', 60);
            $table->char('payload_sha256', 64);
            $table->string('result', 40)->nullable();       // applied | ignored | unknown_payment | error
            $table->text('error')->nullable();
            $table->foreignId('payment_id')->nullable()->index();
            $table->timestamp('received_at', 6);
            $table->timestamp('processed_at', 6)->nullable();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500);
            $table->string('status', 20)->index();           // requested | rejected | processing | processed | failed
            $table->foreignId('requested_by');
            $table->foreignId('decided_by')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->string('provider_refund_id', 64)->nullable()->unique();
            $table->string('provider_status', 30)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('user_id')->index();
            $table->string('provider_dispute_id', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('held_minor')->default(0);
            $table->string('status', 20)->index();           // open | under_review | won | lost | closed
            $table->string('reason_code', 80)->nullable();
            $table->timestamp('respond_by')->nullable();
            $table->timestamps();
        });

        // Problems found by automated checks that need a human (amount mismatch, unknown payment, ...).
        Schema::create('reconciliation_findings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 60)->index();
            $table->string('severity', 10);                  // info | warning | critical
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_ref', 64)->nullable();
            $table->json('details')->nullable();
            $table->string('dedupe_key', 160)->unique();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reconciliation_findings', 'disputes', 'refunds', 'payment_events', 'payments', 'reservations', 'order_items', 'orders', 'recharge_packages', 'ledger_entries', 'ledger_transactions', 'ledger_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
