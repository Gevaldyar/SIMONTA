<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'simonta:make-admin';

    protected $description = 'Create a SIMONTA administrator account';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama administrator'));

        $email = strtolower(
            trim((string) $this->ask('Email administrator'))
        );

        $password = (string) $this->secret(
            'Password administrator (minimal 12 karakter)'
        );

        $passwordConfirmation = (string) $this->secret(
            'Konfirmasi password'
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->confirm(
            "Buat akun administrator untuk {$email}?",
            true
        )) {
            $this->warn('Pembuatan akun dibatalkan.');

            return self::SUCCESS;
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->info('Administrator berhasil dibuat.');
        $this->line('Email: '.$user->email);

        return self::SUCCESS;
    }
}