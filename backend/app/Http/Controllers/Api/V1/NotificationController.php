<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** App: FCM device registration and the in-app notification inbox. */
class NotificationController extends Controller
{
    public static function item(object $n): array
    {
        return ['id' => $n->id, 'kind' => $n->kind, 'ref' => $n->ref, 'title' => $n->title, 'body' => $n->body, 'link' => $n->link, 'read' => $n->read_at !== null,
            'created_at' => CommerceController::iso($n->created_at)];
    }

    public function register(Request $r): Response
    {
        $d = $r->validate(['token' => ['required', 'string', 'min:20', 'max:512'], 'platform' => ['nullable', 'in:android']]);
        $hash = hash('sha256', $d['token']);
        // A token belongs to one device; if another account signed in on it, the token moves.
        DB::table('push_tokens')->upsert([['user_id' => $r->user()->id, 'token' => $d['token'], 'token_hash' => $hash, 'platform' => 'android', 'last_seen_at' => now(), 'failed_at' => null, 'created_at' => now(), 'updated_at' => now()]],
            ['token_hash'], ['user_id', 'last_seen_at', 'failed_at', 'updated_at']);
        if (DB::table('push_tokens')->where('user_id', $r->user()->id)->count() > 10) {
            DB::table('push_tokens')->where('user_id', $r->user()->id)->orderBy('last_seen_at')->limit(1)->delete();
        }

        return response()->noContent();
    }

    public function unregister(Request $r): Response
    {
        $d = $r->validate(['token' => ['required', 'string', 'max:512']]);
        DB::table('push_tokens')->where('token_hash', hash('sha256', $d['token']))->where('user_id', $r->user()->id)->delete();

        return response()->noContent();
    }

    public function index(Request $r): JsonResponse
    {
        $q = DB::table('user_notifications')->where('user_id', $r->user()->id);

        return response()->json(['data' => (clone $q)->orderByDesc('id')->limit(100)->get()->map(fn ($n) => self::item($n)), 'unread' => (clone $q)->whereNull('read_at')->count()]);
    }

    public function read(Request $r, int $id): Response
    {
        $q = DB::table('user_notifications')->where('id', $id)->where('user_id', $r->user()->id);
        abort_unless((clone $q)->exists(), 404);
        $q->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $r): Response
    {
        DB::table('user_notifications')->where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }
}
