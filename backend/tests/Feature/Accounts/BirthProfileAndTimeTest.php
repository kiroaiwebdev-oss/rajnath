<?php

namespace Tests\Feature\Accounts;

use App\Domain\Astro\Time\LocalTimeResolver;
use App\Models\BirthProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BirthProfileAndTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('places')->insert([
            ['id' => 1260086, 'name' => 'Patna', 'ascii_name' => 'Patna', 'admin1' => 'Bihar', 'country_code' => 'IN', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'population' => 1684297],
            ['id' => 5128581, 'name' => 'New York City', 'ascii_name' => 'New York City', 'admin1' => 'New York', 'country_code' => 'US', 'country' => 'United States', 'lat' => 40.71427, 'lng' => -74.00597, 'tz_name' => 'America/New_York', 'population' => 8804190],
            ['id' => 2643743, 'name' => 'London', 'ascii_name' => 'London', 'admin1' => 'England', 'country_code' => 'GB', 'country' => 'United Kingdom', 'lat' => 51.50853, 'lng' => -0.12574, 'tz_name' => 'Europe/London', 'population' => 8961989],
            ['id' => 1269843, 'name' => 'Hyderabad', 'ascii_name' => 'Hyderabad', 'admin1' => 'Telangana', 'country_code' => 'IN', 'country' => 'India', 'lat' => 17.38405, 'lng' => 78.45636, 'tz_name' => 'Asia/Kolkata', 'population' => 3597816],
            ['id' => 1176734, 'name' => 'Hyderabad', 'ascii_name' => 'Hyderabad', 'admin1' => 'Sindh', 'country_code' => 'PK', 'country' => 'Pakistan', 'lat' => 25.39242, 'lng' => 68.37366, 'tz_name' => 'Asia/Karachi', 'population' => 1386330],
        ]);
    }

    public function test_historical_timezones_and_dst(): void
    {
        $r = new LocalTimeResolver;
        // India normal: IST +05:30.
        $a = $r->resolve('1990-05-14', '06:30', 'Asia/Kolkata');
        $this->assertSame(['1990-05-14 01:00:00', 19800, 'exact'], [$a['utc']->format('Y-m-d H:i:s'), $a['offset'], $a['resolution']]);
        // India WWII war time: +06:30 (1942-09-01 to 1945-10-15).
        $b = $r->resolve('1943-06-01', '12:00', 'Asia/Kolkata');
        $this->assertSame([23400, '1943-06-01 05:30:00'], [$b['offset'], $b['utc']->format('Y-m-d H:i:s')]);
        // New York DST gap 2021-03-14 02:30 does not exist → shifted.
        $c = $r->resolve('2021-03-14', '02:30', 'America/New_York');
        $this->assertSame('gap_shifted', $c['resolution']);
        $this->assertSame('2021-03-14 07:30:00', $c['utc']->format('Y-m-d H:i:s')); // = 03:30 EDT
        // New York fall-back 2021-11-07 01:30 happens twice.
        $d1 = $r->resolve('2021-11-07', '01:30', 'America/New_York', 'earlier');
        $d2 = $r->resolve('2021-11-07', '01:30', 'America/New_York', 'later');
        $this->assertSame(['ambiguous_earlier', '2021-11-07 05:30:00'], [$d1['resolution'], $d1['utc']->format('Y-m-d H:i:s')]);
        $this->assertSame(['ambiguous_later', '2021-11-07 06:30:00'], [$d2['resolution'], $d2['utc']->format('Y-m-d H:i:s')]);
        // London summer time.
        $this->assertSame(3600, $r->resolve('2000-07-01', '10:00', 'Europe/London')['offset']);
    }

    public function test_invalid_input_rejected(): void
    {
        $r = new LocalTimeResolver;
        foreach ([['2021-02-30', '10:00', 'Asia/Kolkata'], ['2021-01-01', '25:00', 'Asia/Kolkata'], ['2021-01-01', '10:00', 'Mars/Olympus']] as $args) {
            try {
                $r->resolve(...$args);
                $this->fail('accepted '.implode(' ', $args));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function customer(string $email = 'c@example.com'): User
    {
        $u = new User(['name' => 'C', 'email' => $email]);
        $u->save();

        return $u;
    }

    public function test_create_profile_via_website_resolves_utc_and_flags_dst(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/account/birth-profiles', [
            'label' => 'self', 'name' => 'Ravi', 'gender' => 'male', 'birth_date' => '1990-05-14', 'time_accuracy' => 'exact', 'birth_time' => '06:30', 'place_id' => 1260086,
        ])->assertRedirect('/account/birth-profiles');
        $p = BirthProfile::firstOrFail();
        $this->assertSame('1990-05-14 01:00:00', $p->birth_utc->format('Y-m-d H:i:s'));
        $this->assertSame('Patna, Bihar, India', $p->place_label);
        $this->assertTrue($p->is_primary);

        $this->post('/account/birth-profiles', ['label' => 'child', 'name' => 'NY kid', 'birth_date' => '2021-03-14', 'time_accuracy' => 'exact', 'birth_time' => '02:30', 'place_id' => 5128581])
            ->assertSessionHas('tz_note');
        $this->assertSame('gap_shifted', BirthProfile::where('name', 'NY kid')->value('tz_resolution'));
        $this->assertFalse((bool) BirthProfile::where('name', 'NY kid')->value('is_primary'));
    }

    public function test_unknown_time_and_validation(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/account/birth-profiles', ['label' => 'parent', 'name' => 'Ma', 'birth_date' => '1960-01-01', 'time_accuracy' => 'unknown', 'place_id' => 1260086])->assertSessionHasNoErrors();
        $this->assertNull(BirthProfile::firstOrFail()->birth_utc);
        $this->post('/account/birth-profiles', ['label' => 'self', 'name' => 'X', 'birth_date' => '1990-01-01', 'time_accuracy' => 'exact', 'place_id' => 1260086])->assertSessionHasErrors('birth_time');
        $this->post('/account/birth-profiles', ['label' => 'self', 'name' => 'X', 'birth_date' => now()->addDay()->format('Y-m-d'), 'time_accuracy' => 'unknown', 'place_id' => 1260086])->assertSessionHasErrors('birth_date');
        $this->post('/account/birth-profiles', ['label' => 'self', 'name' => 'X', 'birth_date' => '1990-01-01', 'time_accuracy' => 'unknown', 'place_id' => 999])->assertSessionHasErrors('place_id');
    }

    public function test_profiles_are_private_to_their_owner(): void
    {
        $a = $this->customer('a@example.com');
        $b = $this->customer('b@example.com');
        $this->actingAs($a)->post('/account/birth-profiles', ['label' => 'self', 'name' => 'Secret A', 'birth_date' => '1990-01-01', 'time_accuracy' => 'unknown', 'place_id' => 1260086]);
        $p = BirthProfile::firstOrFail();

        $this->actingAs($b)->get("/account/birth-profiles/{$p->ref}/edit")->assertNotFound();
        $this->actingAs($b)->put("/account/birth-profiles/{$p->ref}", ['label' => 'self', 'name' => 'Hacked', 'birth_date' => '1990-01-01', 'time_accuracy' => 'unknown', 'place_id' => 1260086])->assertNotFound();
        $this->actingAs($b)->delete("/account/birth-profiles/{$p->ref}")->assertNotFound();
        $this->actingAs($b)->get('/account/birth-profiles')->assertDontSee('Secret A');
        $this->assertSame('Secret A', $p->fresh()->name);

        $tb = $b->createToken('d', ['customer'])->plainTextToken;
        $this->withToken($tb)->getJson("/api/v1/birth-profiles/{$p->ref}")->assertNotFound();
        $this->withToken($tb)->deleteJson("/api/v1/birth-profiles/{$p->ref}")->assertNotFound();
        $this->assertNotNull($p->fresh());
    }

    public function test_primary_switches_and_delete_reassigns(): void
    {
        $u = $this->customer();
        $this->actingAs($u);
        foreach (['One', 'Two'] as $n) {
            $this->post('/account/birth-profiles', ['label' => 'other', 'name' => $n, 'birth_date' => '1990-01-01', 'time_accuracy' => 'unknown', 'place_id' => 1260086, 'is_primary' => $n === 'Two' ? '1' : null]);
        }
        $this->assertSame(['Two'], BirthProfile::where('is_primary', true)->pluck('name')->all());
        $this->delete('/account/birth-profiles/'.BirthProfile::where('name', 'Two')->value('ref'));
        $this->assertSame(['One'], BirthProfile::where('is_primary', true)->pluck('name')->all());
    }

    public function test_place_search_prefers_india_and_supports_region_hint(): void
    {
        $r = $this->getJson('/places/search?q=hyder')->assertOk()->json('data');
        $this->assertSame('Hyderabad, Telangana, India', $r[0]['label']);
        $this->assertSame('Asia/Karachi', $this->getJson('/places/search?q=Hyderabad, Sindh')->json('data.0.tz'));
        $this->assertSame([], $this->getJson('/places/search?q=a')->json('data'));
        $this->assertSame([], $this->getJson('/places/search?q=%25%25')->json('data'));
    }
}
