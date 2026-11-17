<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_user_is_verified_and_has_the_requested_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Test Admin',
            '--email' => 'admin@example.test',
            '--password' => 'correct-horse-battery',
            '--role' => 'admin',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Test Admin', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_role_defaults_to_user(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Test User',
            '--email' => 'user@example.test',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $user = User::where('email', 'user@example.test')->firstOrFail();

        $this->assertSame('user', $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
    }
}
