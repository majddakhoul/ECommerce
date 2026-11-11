<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class LoginLockoutTest extends ApiTestCase
{
    private function attempt(string $email, string $password = 'wrong')
    {
        return $this->postJson($this->url('auth/login'), ['email' => $email, 'password' => $password]);
    }

    public function test_account_is_locked_after_the_maximum_number_of_failed_attempts(): void
    {
        $user = User::factory()->create();
        $max = config('api.login_max_attempts');

        for ($i = 1; $i < $max; $i++) {
            $this->attempt($user->email)->assertStatus(401);
        }

        $this->attempt($user->email)->assertStatus(423)->assertJsonPath('status', false);

        $this->assertTrue($user->fresh()->isLocked());
    }

    public function test_locked_account_rejects_even_the_correct_password(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->attempt($user->email, 'password')->assertStatus(423);
    }

    public function test_lock_expires_after_the_lockout_period(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locked_until' => now()->subMinute()])->save();

        $this->attempt($user->email, 'password')->assertOk();
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_successful_login_resets_the_failed_attempts_counter(): void
    {
        $user = User::factory()->create();

        $this->attempt($user->email)->assertStatus(401);
        $this->attempt($user->email)->assertStatus(401);
        $this->assertSame(2, $user->fresh()->failed_login_attempts);

        $this->attempt($user->email, 'password')->assertOk();
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_admin_can_unlock_an_account(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->actingAsApi($this->admin())
            ->postJson($this->url("users/{$user->id}/unlock"))
            ->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->attempt($user->email, 'password')->assertOk();
    }

    public function test_regular_user_cannot_unlock_accounts(): void
    {
        $target = User::factory()->create();
        $target->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->actingAsApi($this->member())
            ->postJson($this->url("users/{$target->id}/unlock"))
            ->assertStatus(403);
    }

    public function test_requests_are_throttled_per_email_and_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->attempt('ghost@example.com')->assertStatus(401);
        }

        $this->attempt('ghost@example.com')->assertStatus(429)->assertHeader('Retry-After');
    }
}
