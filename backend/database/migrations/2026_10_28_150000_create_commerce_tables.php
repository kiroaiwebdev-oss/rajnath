<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Double-entry ledger. Amounts are integer paise. Entries are append-only.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();              // e.g. customer_wallet:42, revenue, gateway_clearing
            $table->string('kind', 30)->index();               // asset | liability | revenue | expense
            $table->foreignId('user_id')->nullable()->index();
            $table->char('currency', 3)->default('INR');
            $table->bigInteger('balance_minor')->default(0);   // cached; debit-positive for assets, credit-positive for liabilities/revenue
            $table->timestamps();
        });
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('kind', 40)->index();
            $table->string('idempotency_key', 120)->unique();
            $table->string('memo', 255)->nullable();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions');
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->enum('direction', ['debit', 'credit']);
            $table->unsignedBigInteger('amount_minor');
            $table->index(['account_id', 'id']);
        });

        Schema::create('recharge_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('bonus_minor')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->index();
            $table->string('kind', 20);                        // service | recharge
            $table->string('status', 20)->index();             // pending_payment | paid | cancelled | refunded | partially_refunded | failed
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('wallet_applied_minor')->default(0);
            $table->unsignedBigInteger('payable_minor');
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3)->default('INR');
            $table->json('snapshot');                          // immutable item/price/policy snapshot at purchase
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('order_id')->constrained();
            $table->string('provider', 20)->default('razorpay');
            $table->string('provider_order_id', 60)->unique();
            $table->string('provider_payment_id', 60)->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->index();             // created | authorized | captured | failed | ambiguous
            $table->string('method', 30)->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id', 80)->unique(); // replay protection
            $table->string('type', 60);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('result', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->unsignedBigInteger('amount_minor');
            $table->string('destination', 12);                 // source | wallet
            $table->string('reason', 300);
            $table->string('status', 20)->index();             // requested | approved | rejected | processing | processed | failed
            $table->foreignId('requested_by');
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('provider_refund_id', 60)->nullable()->unique();
            $table->string('error', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_findings', function (Blueprint $table) {
            $table->id();
            $table->string('check', 60);
            $table->string('severity', 10);                    // info | warning | critical
            $table->string('subject', 120)->nullable();
            $table->text('detail');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reconciliation_findings', 'refunds', 'payment_events', 'payments', 'orders', 'recharge_packages', 'ledger_entries', 'ledger_transactions', 'ledger_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
