<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_user_has_fillable_attributes(): void
    {
        $user = new User();

        $this->assertEquals(['name', 'email', 'password', 'role'], $user->getFillable());
    }

    public function test_user_hides_sensitive_attributes(): void
    {
        $user = new User();
        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
        $this->assertContains('two_factor_recovery_codes', $hidden);
        $this->assertContains('two_factor_secret', $hidden);
    }

    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plain-text-password',
        ]);

        $this->assertNotEquals('plain-text-password', $user->password);
        $this->assertTrue(password_verify('plain-text-password', $user->password));
    }

    public function test_user_email_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    public function test_user_has_profile_photo_url_appended(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayHasKey('profile_photo_url', $array);
    }

    public function test_user_has_api_tokens_trait(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'tokens'),
            'User model should use HasApiTokens trait'
        );
    }

    public function test_user_has_two_factor_authenticatable_trait(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'twoFactorQrCodeSvg'),
            'User model should use TwoFactorAuthenticatable trait'
        );
    }
}
