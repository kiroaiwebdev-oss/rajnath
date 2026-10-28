<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only, hash-chained audit trail. Application code never updates or deletes rows.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->index();
            $table->string('actor_label', 120)->nullable(); // e.g. "system", "cli", user ref
            $table->string('action', 120)->index();
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_ref', 64)->nullable();
            $table->json('context')->nullable();            // redacted before storage
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();
            $table->timestamp('created_at', 6);
            $table->index(['subject_type', 'subject_ref']);
        });

        // Single-row lock target that serialises audit-chain appends.
        Schema::create('audit_chain_heads', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('last_hash', 64);
            $table->unsignedBigInteger('last_id')->default(0);
        });
        \Illuminate\Support\Facades\DB::table('audit_chain_heads')->insert([
            'id' => 1, 'last_hash' => \App\Support\Audit\AuditLogger::GENESIS, 'last_id' => 0,
        ]);

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();
            $table->string('group', 60)->index();
            $table->longText('value')->nullable();   // JSON; encrypted when is_secret
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        // One-time tokens for owner bootstrap and staff invitations (only hashes stored).
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('email');
            $table->string('name');
            $table->json('roles');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_chain_heads');
        Schema::dropIfExists('audit_logs');
    }
};
