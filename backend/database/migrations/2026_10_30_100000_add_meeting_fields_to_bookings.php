<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // livekit (in-app) | zoom | google_meet | manual
            $table->string('meeting_provider', 12)->nullable();
            $table->string('meeting_external_id', 191)->nullable();
            $table->string('meeting_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['meeting_provider', 'meeting_external_id', 'meeting_error']));
    }
};
