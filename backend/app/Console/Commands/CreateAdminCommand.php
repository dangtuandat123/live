<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {--email= : Email of existing user to promote} {--new : Create a new admin user}';

    protected $description = 'Tạo hoặc nâng cấp user thành admin';

    public function handle(): int
    {
        if ($this->option('new')) {
            return $this->createNewAdmin();
        }

        $email = $this->option('email') ?? $this->ask('Nhập email của user cần nâng cấp thành admin');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Không tìm thấy user với email: {$email}");

            if ($this->confirm('Bạn có muốn tạo admin mới không?')) {
                return $this->createNewAdmin($email);
            }

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->warn("User {$email} đã là admin rồi.");
            return self::SUCCESS;
        }

        $user->update(['role' => 'admin']);
        $this->info("Đã nâng cấp {$user->name} ({$email}) thành admin.");

        return self::SUCCESS;
    }

    private function createNewAdmin(?string $email = null): int
    {
        $name = $this->ask('Tên admin', 'Admin');
        $email = $email ?? $this->ask('Email admin');
        $password = $this->secret('Mật khẩu');

        if (User::where('email', $email)->exists()) {
            $this->error("Email {$email} đã tồn tại. Dùng lệnh không có --new để nâng cấp.");
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info("Đã tạo admin: {$name} ({$email})");

        return self::SUCCESS;
    }
}
