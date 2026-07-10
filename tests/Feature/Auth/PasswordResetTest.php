<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use ReflectionClass;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'buyer@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => ' buyer@example.com ',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password reset token has been sent to your email.',
            ]);

        Notification::assertSentTo($user, PasswordResetCodeNotification::class, function ($notification) {
            $reflection = new ReflectionClass($notification);
            $token = $reflection->getProperty('token')->getValue($notification);

            return is_string($token) && $token !== '';
        });
    }

    public function test_reset_password_changes_password_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'email' => ' buyer@example.com ',
            'token' => ' '.$token.' ',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_reset_password_accepts_code_and_confirm_password_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $code = Password::broker()->createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'buyer@example.com',
            'code' => $code,
            'password' => 'new-password',
            'confirm_password' => 'new-password',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'buyer@example.com',
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'buyer@example.com',
            'token' => 'not-a-real-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ]);
    }
}
