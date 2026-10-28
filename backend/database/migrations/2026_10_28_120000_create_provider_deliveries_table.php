<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outbound email/SMS attempts. "accepted" means the provider accepted the message;
        // handset/inbox delivery is only known once delivery webhooks are wired (later phase).
        Schema::create('provider_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);            // email | sms
            $table->string('provider', 30);           // smtp | twilio | fast2sms | log
            $table->string('purpose', 40);            // test | login_otp | ...
            $table->string('to_masked', 80);
            $table->char('to_hash', 64)->index();     // for support lookups without storing the address
            $table->string('status', 20);             // accepted | failed
            $table->string('provider_message_id', 120)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_deliveries');
    }
};
