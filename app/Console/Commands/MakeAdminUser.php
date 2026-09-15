<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates or resets the single admin login (see RequireAuthenticationUnlessDemoMode
 * - homie has no per-user data model, so there's exactly one credential that
 * matters here, not a user-management system). Upserts by email so re-running
 * this against an existing email changes its password instead of creating a
 * second row.
 */
class MakeAdminUser extends Command
{
    protected $signature = 'homie:make-admin {--email=} {--password=} {--name=Admin}';

    protected $description = 'Create or reset the admin login used to access this dashboard';

    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Admin email');
        $password = $this->option('password') ?? $this->secret('Admin password');
        $name = $this->option('name') ?: 'Admin';

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'name' => $name],
            ['email' => 'required|email', 'password' => 'required|string|min:8', 'name' => 'required|string|max:255'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $user = User::query()->updateOrCreate(
            ['email' => $validated['email']],
            ['name' => $validated['name'], 'password' => $validated['password']],
        );

        $this->info("Admin login ready for {$user->email}.");

        return self::SUCCESS;
    }
}
