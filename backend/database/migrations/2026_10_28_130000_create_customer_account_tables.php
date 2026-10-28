<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable()->after('display_timezone');
            $table->timestamp('terms_accepted_at')->nullable()->after('notification_prefs');
        });

        // One-time codes for email/SMS. Only an HMAC of the code is stored; the destination is
        // stored as a keyed hash (for rate limits) plus encrypted value (for sending/resend).
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->string('channel', 10);                 // email | sms
            $table->string('purpose', 30);                 // login | change_contact | reauth
            $table->char('destination_hash', 64)->index();
            $table->text('destination');                   // encrypted
            $table->foreignId('user_id')->nullable()->index();
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('sends')->default(1);
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();   // GeoNames id
            $table->string('name', 200);
            $table->string('ascii_name', 200)->index();
            $table->string('admin1', 120)->nullable();
            $table->char('country_code', 2)->index();
            $table->string('country', 120);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('tz_name', 64);
            $table->unsignedBigInteger('population')->default(0)->index();
        });

        Schema::create('birth_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ref')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);                   // self | spouse | child | parent | other
            $table->string('name', 120);
            $table->string('gender', 10)->nullable();      // male | female | other
            $table->date('birth_date');
            $table->time('birth_time')->nullable();
            $table->string('time_accuracy', 12);           // exact | approximate | unknown
            $table->unsignedBigInteger('place_id')->nullable();
            $table->string('place_label', 255);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('tz_name', 64);
            $table->dateTime('birth_utc')->nullable();     // resolved instant (null when time unknown)
            $table->integer('utc_offset_seconds')->nullable();
            $table->string('tz_resolution', 20)->nullable(); // exact | ambiguous_earlier | ambiguous_later | gap_shifted
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_primary']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);                    // terms_privacy | marketing
            $table->string('version', 20);
            $table->timestamp('granted_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->index(['user_id', 'type']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('platform', 20)->nullable()->after('name');
            $table->string('ip', 45)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $t) => $t->dropColumn(['platform', 'ip']));
        Schema::dropIfExists('consents');
        Schema::dropIfExists('birth_profiles');
        Schema::dropIfExists('places');
        Schema::dropIfExists('otp_challenges');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['notification_prefs', 'terms_accepted_at']));
    }
};
