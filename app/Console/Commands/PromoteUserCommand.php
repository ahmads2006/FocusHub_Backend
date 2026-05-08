<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class PromoteUserCommand extends Command
{
    protected $signature = 'user:promote {email : البريد الإلكتروني للمستخدم} {role : اسم الدور (مثل super-admin)}';

    protected $description = 'ترقية مستخدم إلى دور معيّن من قاعدة البيانات';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("المستخدم غير موجود: {$email}");
            return 1;
        }

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $this->error("الدور غير موجود: {$roleName}");
            $this->info('الأدوار المتاحة: ' . Role::pluck('name')->implode(', '));
            return 1;
        }

        $user->syncRoles([$roleName]);
        $user->update(['role' => $roleName]);
        $this->info("تم ترقية {$user->name} ({$email}) إلى دور: {$roleName}");

        return 0;
    }
}
