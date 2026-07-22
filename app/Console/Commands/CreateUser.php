<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'user:create
                          {--name= : The name of the user}
                          {--email= : The email of the user}
                          {--password= : The password for the user}
                          {--role=user : The role of the user (admin/user/internal_evaluator/external_evaluator)}';

    protected $description = 'Create a new user';

    public function handle()
    {
        $name = $this->option('name') ?? $this->ask('What is the user\'s name?');
        $email = $this->option('email') ?? $this->ask('What is the user\'s email?');
        $password = $this->option('password') ?? $this->secret('What is the user\'s password?');
        $role = $this->option('role');

        // Validate role
        $validRoles = ['admin', 'user', 'internal_evaluator', 'external_evaluator'];
        if (! in_array($role, $validRoles)) {
            $role = $this->choice(
                'Select user role',
                $validRoles,
                1 // Default to 'user'
            );
        }

        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'role' => $role,
            ]);

            $this->info('User created successfully!');
            $this->table(
                ['Name', 'Email', 'Role'],
                [[$user->name, $user->email, $user->role]]
            );
        } catch (\Exception $e) {
            $this->error('Failed to create user: '.$e->getMessage());
        }
    }
}
