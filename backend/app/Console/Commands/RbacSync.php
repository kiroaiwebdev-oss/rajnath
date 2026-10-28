<?php

namespace App\Console\Commands;

use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSync extends Command
{
    protected $signature = 'arj:rbac-sync';

    protected $description = 'Create/update roles and permissions from config/rbac.php (idempotent, no users created).';

    public function handle(AuditLogger $audit): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $perms = config('rbac.permissions');
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Permission::whereNotIn('name', $perms)->where('guard_name', 'web')->delete();

        foreach (config('rbac.roles') as $role => $granted) {
            $r = Role::findOrCreate($role, 'web');
            $r->syncPermissions($granted === ['*'] ? $perms : $granted);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $audit->record('rbac.synced', null, ['roles' => array_keys(config('rbac.roles')), 'permissions' => count($perms)], actorLabel: 'cli');
        $this->info('Roles: '.count(config('rbac.roles')).', permissions: '.count($perms));

        return self::SUCCESS;
    }
}
