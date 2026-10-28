<?php

namespace App\Domain\Identity;

use App\Domain\Astro\Time\LocalTimeResolver;
use App\Models\BirthProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Validation and persistence of birth profiles shared by the website and the API. */
class BirthProfiles
{
    public function __construct(private LocalTimeResolver $time) {}

    public static function rules(): array
    {
        return [
            'label' => ['required', Rule::in(array_keys(BirthProfile::LABELS))],
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'birth_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1800-01-01', 'before_or_equal:today'],
            'time_accuracy' => ['required', Rule::in(array_keys(BirthProfile::ACCURACY))],
            'birth_time' => ['nullable', 'required_unless:time_accuracy,unknown', 'date_format:H:i'],
            'place_id' => ['required', 'integer', 'exists:places,id'],
            'dst_choice' => ['nullable', Rule::in(['earlier', 'later'])],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function save(User $user, array $data, ?BirthProfile $profile = null): BirthProfile
    {
        $place = DB::table('places')->where('id', $data['place_id'])->first();
        if (! $place) {
            throw ValidationException::withMessages(['place_id' => 'Choose a place from the list.']);
        }

        return DB::transaction(function () use ($user, $data, $profile, $place) {
            User::whereKey($user->id)->lockForUpdate()->first();
            if (! $profile && BirthProfile::where('user_id', $user->id)->count() >= BirthProfile::MAX_PER_USER) {
                throw ValidationException::withMessages(['name' => 'You can save up to '.BirthProfile::MAX_PER_USER.' birth profiles.']);
            }
            $p = $profile ?? new BirthProfile;
            $p->fill([
                'label' => $data['label'], 'name' => trim($data['name']), 'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'], 'time_accuracy' => $data['time_accuracy'],
                'birth_time' => $data['time_accuracy'] === 'unknown' ? null : $data['birth_time'].':00',
                'place_id' => $place->id,
                'place_label' => implode(', ', array_filter([$place->name, $place->admin1, $place->country])),
                'lat' => $place->lat, 'lng' => $place->lng, 'tz_name' => $place->tz_name,
            ]);
            $p->user_id = $user->id;
            if ($p->birth_time) {
                try {
                    $r = $this->time->resolve($data['birth_date'], substr($p->birth_time, 0, 5), $place->tz_name, $data['dst_choice'] ?? 'earlier');
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['birth_time' => $e->getMessage()]);
                }
                $p->birth_utc = $r['utc']->format('Y-m-d H:i:s');
                $p->utc_offset_seconds = $r['offset'];
                $p->tz_resolution = $r['resolution'];
            } else {
                $p->birth_utc = null;
                $p->utc_offset_seconds = null;
                $p->tz_resolution = null;
            }
            $hasPrimary = BirthProfile::where('user_id', $user->id)->where('is_primary', true)->when($p->exists, fn ($q) => $q->whereKeyNot($p->id))->exists();
            $p->is_primary = ! empty($data['is_primary']) || ! $hasPrimary;
            $p->save();
            if ($p->is_primary) {
                BirthProfile::where('user_id', $user->id)->whereKeyNot($p->id)->update(['is_primary' => false]);
            }

            return $p;
        });
    }

    public function delete(BirthProfile $p): void
    {
        DB::transaction(function () use ($p) {
            $wasPrimary = $p->is_primary;
            $uid = $p->user_id;
            $p->delete();
            if ($wasPrimary) {
                BirthProfile::where('user_id', $uid)->orderBy('id')->limit(1)->update(['is_primary' => true]);
            }
        });
    }

    public static function resolutionNote(?string $r): ?string
    {
        return match ($r) {
            'ambiguous_earlier', 'ambiguous_later' => 'Clocks went back at this place on this date, so this time happened twice. We used the '.($r === 'ambiguous_later' ? 'second' : 'first').' occurrence — edit the profile to change it.',
            'gap_shifted' => 'Clocks moved forward at this place on this date, so this exact time did not exist. We treated it as the time just after the change. Please confirm the birth time.',
            default => null,
        };
    }
}
