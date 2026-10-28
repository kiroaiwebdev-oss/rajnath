<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Offering;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryApiController extends Controller
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $d = $request->validate([
            'offering' => ['nullable', 'string', 'exists:offerings,slug'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'mode' => ['nullable', Rule::in(array_keys(\App\Models\OfferingVariant::MODES))],
            'language' => ['nullable', Rule::in(['Hindi', 'Maithili', 'Sanskrit', 'English'])],
            'preferred_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);
        $digits = preg_replace('/[\s\-()]/', '', $d['phone']);
        $phone = preg_match('/^(?:\+?91|0)?([6-9]\d{9})$/', $digits, $m) ? '+91'.$m[1] : (preg_match('/^\+[1-9]\d{7,14}$/', $digits) ? $digits : null);
        if (! $phone) {
            return response()->json(['message' => 'Invalid phone', 'errors' => ['phone' => ['Enter a 10-digit mobile number, or include the country code.']]], 422);
        }
        $user = auth('sanctum')->user();
        $e = Enquiry::create([
            'user_id' => $user?->id, 'offering_id' => isset($d['offering']) ? Offering::where('slug', $d['offering'])->value('id') : null,
            'name' => $d['name'], 'phone_e164' => $phone, 'email' => $d['email'] ?? null, 'mode' => $d['mode'] ?? null,
            'language' => $d['language'] ?? null, 'preferred_date' => $d['preferred_date'] ?? null, 'message' => $d['message'] ?? null, 'ip' => $request->ip(),
        ]);
        $audit->record('enquiry.created', $e, ['via' => 'api'], $user, $user ? null : 'guest');

        return response()->json(['data' => ['ref' => $e->ref, 'status' => 'received']], 201);
    }
}
