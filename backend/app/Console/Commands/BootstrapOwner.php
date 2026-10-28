<?php

namespace App\Console\Commands;

use App\Domain\Identity\StaffInvitations;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Secure owner bootstrap: there are no default credentials. Run on the server:
 *   php artisan arj:bootstrap-owner owner@example.com "Owner Name"
 * It prints a one-time link (valid 30 minutes) where the owner chooses a password and enrols
 * TOTP MFA. Refuses to run once an owner exists unless --force-additional is given.
 */
class BootstrapOwner extends Command
{
    protected $signature = 'arj:bootstrap-owner {email} {name} {--force-additional : Allow creating an invitation even if an owner already exists}';

    protected $description = 'Create a one-time owner setup link (no default passwords).';

    public function handle(StaffInvitations $invitations): int
    {
        $this->call('arj:rbac-sync');
        $hasOwner = User::role('owner')->exists();
        if ($hasOwner && ! $this->option('force-additional')) {
            $this->error('An owner already exists. Use the admin Team screen, or --force-additional for recovery.');

            return self::FAILURE;
        }
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');

            return self::FAILURE;
        }
        [$invitation, $token] = $invitations->create($email, $this->argument('name'), ['owner'], null, 30);
        $this->line('One-time owner setup link (expires in 30 minutes, shown once):');
        $this->line(url('/staff/invitation/'.$token));

        return self::SUCCESS;
    }
}
