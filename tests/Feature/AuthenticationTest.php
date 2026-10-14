<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'uma-senha-segura-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_registration_normalizes_email_hashes_password_and_rotates_session(): void
    {
        $this->get(route('register'));
        $session = session()->getId();

        $this->post(route('register'), [
            'name' => 'Ana', 'email' => ' ANA@example.com ',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $user = User::sole();
        $this->assertSame('ana@example.com', $user->email);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($session, session()->getId());
    }

    public function test_duplicate_email_does_not_replace_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->post(route('register'), [
            'name' => 'Outra pessoa', 'email' => 'ANA@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertGuest();
    }

    public function test_registration_rejects_weak_unconfirmed_and_oversized_passwords(): void
    {
        foreach ([['short', 'short'], [self::PASSWORD, 'different'], [str_repeat('á', 37), str_repeat('á', 37)]] as [$password, $confirmation]) {
            $this->post(route('register'), [
                'name' => 'Ana', 'email' => 'ana@example.com',
                'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_login_authenticates_and_rotates_session(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->get(route('login'));
        $session = session()->getId();

        $this->post(route('login'), ['email' => strtoupper($user->email), 'password' => self::PASSWORD])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($session, session()->getId());
    }

    public function test_wrong_password_returns_generic_error_and_is_not_flashed(): void
    {
        $user = User::factory()->create();
        $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.'])
            ->assertSessionMissing('_old_input.password')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();
    }

    public function test_login_attempts_are_limited_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_dashboard_escapes_user_input_and_is_not_cached(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'));
        $session = session()->getId();
        $token = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($session, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('logout'))->assertStatus(405);
    }

    public function test_logout_requires_csrf_protection(): void
    {
        Route::getRoutes()->getByName('logout')->middleware(EnforceCsrfForTest::class);
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_authentication_forms_render_without_cache(): void
    {
        foreach (['register', 'login', 'password.request'] as $name) {
            $this->get(route($name))->assertOk()->assertSee('name="_token"', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->get(route('password.reset', ['token' => 'example', 'email' => 'ana@example.com']))
            ->assertOk()->assertSee('ana@example.com');
    }

    public function test_password_recovery_has_the_same_response_for_unknown_emails_and_throttles_resends(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $status = session('status');
        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', $status);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $status);
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_recovery_endpoint_limits_requests_per_ip(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'other@example.com'])->assertStatus(429);
    }

    public function test_reset_password_changes_hash_rotates_remember_token_and_consumes_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $token = Password::createToken($user);
        $data = ['token' => $token, 'email' => $user->email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertGuest();
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    }

    public function test_invalid_and_expired_reset_tokens_do_not_change_the_password(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $data = ['email' => $user->email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
        $this->post(route('password.update'), ['token' => 'invalid'] + $data)->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post(route('password.update'), ['token' => $token] + $data)->assertSessionHasErrors('email');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_password_reset_invalidates_an_existing_authenticated_session(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->get(route('dashboard'))->assertOk();
        $token = Password::createToken($user);
        $this->assertTrue(app(AuthenticationService::class)->resetPassword([
            'email' => $user->email, 'token' => $token,
            'password' => 'outra-senha-segura-456', 'password_confirmation' => 'outra-senha-segura-456',
        ]));
        Auth::guard()->forgetUser();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

class EnforceCsrfForTest extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
