<?php

namespace Tests\Feature\Accounts;

use App\Mail\OtpMail;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function code(): string
    {
        $c = null;
        Mail::assertSent(OtpMail::class, function ($m) use (&$c) { $c = $m->code; return true; });

        return $c;
    }

    private function signUp(string $email = 'app@example.com', string $device = 'Pixel 8'): array
    {
        Mail::fake();
        $start = $this->postJson('/api/v1/auth/otp/start', ['identifier' => $email])->assertCreated()->json();

        return $this->postJson('/api/v1/auth/otp/verify', ['challenge' => $start['challenge'], 'code' => $this->code(), 'device_name' => $device, 'platform' => 'android', 'name' => 'App User', 'accept_terms' => true])->json();
    }

    public function test_full_mobile_sign_in_flow(): void
    {
        Mail::fake();
        $start = $this->postJson('/api/v1/auth/otp/start', ['identifier' => 'app@example.com'])->assertCreated()
            ->assertJsonStructure(['challenge', 'channel', 'sent_to', 'is_new_account', 'expires_in', 'resend_in'])->json();
        $this->assertTrue($start['is_new_account']);
        $code = $this->code();

        $this->postJson('/api/v1/auth/otp/verify', ['challenge' => $start['challenge'], 'code' => $code, 'device_name' => 'Pixel', 'platform' => 'android'])
            ->assertStatus(422)->assertJsonValidationErrors('terms');
        $res = $this->postJson('/api/v1/auth/otp/verify', ['challenge' => $start['challenge'], 'code' => $code, 'device_name' => 'Pixel', 'platform' => 'android', 'name' => 'App User', 'accept_terms' => true])
            ->assertCreated()->assertJsonPath('user.name', 'App User')->assertJsonPath('user.email_verified', true)->json();
        $this->assertStringContainsString('|', $res['token']);

        $this->withToken($res['token'])->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'app@example.com');
        $this->withToken($res['token'])->patchJson('/api/v1/me', ['name' => 'Renamed', 'notification_prefs' => ['offers' => true, 'hack' => true]])
            ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.notification_prefs.offers', true)->assertJsonMissingPath('data.notification_prefs.hack');
        $this->withToken($res['token'])->getJson('/api/v1/me/devices')->assertOk()->assertJsonPath('data.0.platform', 'android')->assertJsonPath('data.0.current', true);

        $this->withToken($res['token'])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($res['token'])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_wrong_code_and_errors_use_stable_shape(): void
    {
        Mail::fake();
        $start = $this->postJson('/api/v1/auth/otp/start', ['identifier' => 'e@example.com'])->json();
        $this->postJson('/api/v1/auth/otp/verify', ['challenge' => $start['challenge'], 'code' => '000000', 'device_name' => 'x', 'platform' => 'android', 'accept_terms' => true])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid');
        $this->postJson('/api/v1/auth/otp/start', ['identifier' => 'bad'])->assertStatus(422)->assertJsonValidationErrors('identifier');
        $this->postJson('/api/v1/auth/otp/resend', ['challenge' => $start['challenge']])->assertStatus(429)->assertJsonPath('error.code', 'too_soon');
    }

    public function test_revoking_device_and_suspension_invalidate_tokens(): void
    {
        $a = $this->signUp('dev@example.com', 'Phone A')['token'];
        $u = User::where('email', 'dev@example.com')->first();
        $b = $u->createToken('Phone B', ['customer'])->plainTextToken;
        $bId = (int) explode('|', $b)[0];

        $this->withToken($a)->deleteJson("/api/v1/me/devices/$bId")->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($b)->getJson('/api/v1/me')->assertUnauthorized();

        $u->forceFill(['status' => 'suspended'])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($a)->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error.code', 'account_inactive');
    }

    public function test_cannot_revoke_someone_elses_device(): void
    {
        $a = $this->signUp('a1@example.com')['token'];
        $other = new User(['name' => 'O', 'email' => 'o@example.com']);
        $other->save();
        $ot = $other->createToken('x', ['customer']);
        $this->withToken($a)->deleteJson('/api/v1/me/devices/'.$ot->accessToken->id)->assertNotFound();
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_token_without_customer_ability_and_staff_tokens_are_refused(): void
    {
        $u = new User(['name' => 'U', 'email' => 'u@example.com']);
        $u->save();
        $this->withToken($u->createToken('x', ['other'])->plainTextToken)->getJson('/api/v1/me')->assertForbidden();
        $s = new User(['name' => 'S', 'email' => 's@example.com']);
        $s->kind = 'staff';
        $s->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($s->createToken('x', ['customer'])->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_token_rejected(): void
    {
        $t = $this->signUp('exp@example.com')['token'];
        $this->travel(91)->days();
        $this->app['auth']->forgetGuards();
        $this->withToken($t)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_birth_profile_crud_via_api(): void
    {
        DB::table('places')->insert(['id' => 1260086, 'name' => 'Patna', 'ascii_name' => 'Patna', 'admin1' => 'Bihar', 'country_code' => 'IN', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'population' => 1]);
        $t = $this->signUp()['token'];
        $p = $this->withToken($t)->postJson('/api/v1/birth-profiles', ['label' => 'self', 'name' => 'Me', 'birth_date' => '1990-05-14', 'time_accuracy' => 'exact', 'birth_time' => '06:30', 'place_id' => 1260086])
            ->assertCreated()->assertJsonPath('data.birth_utc', '1990-05-14T01:00:00Z')->assertJsonPath('data.place.tz', 'Asia/Kolkata')->json('data');
        $this->withToken($t)->putJson('/api/v1/birth-profiles/'.$p['ref'], ['label' => 'self', 'name' => 'Me2', 'birth_date' => '1990-05-14', 'time_accuracy' => 'unknown', 'place_id' => 1260086])
            ->assertOk()->assertJsonPath('data.birth_utc', null);
        $this->withToken($t)->getJson('/api/v1/birth-profiles')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($t)->postJson('/api/v1/birth-profiles', ['name' => ''])->assertStatus(422);
        $this->withToken($t)->deleteJson('/api/v1/birth-profiles/'.$p['ref'])->assertNoContent();
    }

    public function test_public_catalogue_hides_drafts(): void
    {
        $this->seed(CatalogueSeeder::class);
        $list = $this->getJson('/api/v1/catalogue/offerings')->assertOk()->json('data');
        $this->assertCount(12, $list);
        $this->assertNotContains('premium-personalized-kundli', array_column($list, 'slug'));
        $this->getJson('/api/v1/catalogue/offerings/premium-personalized-kundli')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->getJson('/api/v1/catalogue/offerings/janma-kundali-reading')->assertOk()->assertJsonPath('data.title', 'Janma Kundali Reading')
            ->assertJsonPath('data.variants.0.price_minor', null);
        $this->getJson('/api/v1/catalogue/offerings?family=vastu')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/catalogue/families')->assertOk();
    }
}
