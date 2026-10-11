<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Creează sau promovează un utilizator administrator';

    public function handle(): int
    {
        $name = $this->ask('Numele administratorului');
        $email = $this->ask('Adresa de e-mail');
        $password = $this->secret('Parola (minimum 12 caractere)');

        if (! is_string($name) || trim($name) === '' || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Numele, e-mailul valid și o parolă de minimum 12 caractere sunt obligatorii.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => mb_strtolower($email)], ['name' => trim($name), 'password' => $password, 'is_admin' => true]);
        $this->info("Administratorul {$user->email} este gata de autentificare.");

        return self::SUCCESS;
    }
}
