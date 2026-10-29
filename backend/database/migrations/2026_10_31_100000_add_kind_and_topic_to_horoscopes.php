<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horoscope kinds (rashi, nakshatra, Chinese, numerology, tarot) and rashi topics (love, career,
 * finance, health, education). Existing rows become kind "rashi", topic "general".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->string('kind', 12)->default('rashi')->after('id');
            $table->string('topic', 12)->default('general')->after('kind');
            $table->string('period', 12)->change();
        });
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->dropUnique(['period', 'sign', 'starts_on', 'locale']);
            $table->unique(['kind', 'topic', 'period', 'sign', 'starts_on', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->dropUnique(['kind', 'topic', 'period', 'sign', 'starts_on', 'locale']);
        });
        DB::table('horoscopes')->where('kind', '!=', 'rashi')->orWhere('topic', '!=', 'general')->delete();
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->unique(['period', 'sign', 'starts_on', 'locale']);
            $table->dropColumn(['kind', 'topic']);
        });
    }
};
