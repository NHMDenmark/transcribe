<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Sets up a new installation in one go, or checks an existing one: the .env file and the application key, the
 * database (tables and the base data), the folders the application writes to, and the first administrator.
 * Every step only does what is missing, so it can be run again at any time.
 */
class InstallPlatform extends Command
{
    protected $signature = 'platform:install
        {--name= : name of the first administrator}
        {--email= : e-mail of the first administrator}
        {--password= : password of the first administrator (prefer to leave it out and type it when asked)}
        {--no-admin : do not create an administrator}';

    protected $description = 'Prepare a new installation: environment file, database, base data, folders and the first administrator';

    /** folders under public/ that the application creates and writes to when files are uploaded */
    private const WRITABLE = ['storage', 'bootstrap/cache', 'public/avatars', 'public/uploads', 'public/cover_images', 'public/branding'];

    public function handle(): int
    {
        $this->components->info('Platform installation');

        $this->environmentFile();

        if (! $this->database()) {
            return self::FAILURE;
        }

        $this->components->task('Creating or updating the tables', function () {
            Artisan::call('migrate', ['--force' => true]);
        });
        $this->components->task('Adding the base data (language, levels, texts)', function () {
            (new BaseDataSeeder())->run();
        });

        $folders = $this->folders();

        if (! $this->option('no-admin') && ! $this->administrator()) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Done. Next steps:');
        $this->line('  1. Sign in and open "Settings" to upload the logo of your institution.');
        $this->line('  2. Add the connection to your collection database in .env if you will sync data (see docs/configuration.md).');
        $this->line('  3. Create the fields and a first project (docs/administration.md).');
        $this->line('  4. Let people in: set up the mail (MAIL_* in .env) so that they can set a password, and/or Google or GitHub sign-in.');
        $this->line('  5. Add the cron line that drives the scheduler (docs/installation.md, "The scheduler"): the weekly ranking and the automatic sync depend on it.');
        if (! $folders) {
            $this->newLine();
            $this->components->warn('Some folders are not writable by this user. Uploads work only when the web server user can write to them (docs/installation.md, "Permissions").');
        }

        return self::SUCCESS;
    }

    /** .env from the example when missing, and an application key when empty */
    private function environmentFile(): void
    {
        if (! file_exists(base_path('.env')) && file_exists(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->components->info('.env created from .env.example: open it and set the database and APP_URL.');
        }

        if (config('app.key') === null || config('app.key') === '') {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->info('Application key generated.');
        }
    }

    private function database(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->components->error('Cannot connect to the database: ' . $e->getMessage());
            $this->line('  Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env (the database must exist and the user must be allowed to use it).');

            return false;
        }

        return true;
    }

    /** @return bool whether every folder is writable */
    private function folders(): bool
    {
        $ok = true;
        foreach (self::WRITABLE as $folder) {
            $path = base_path($folder);
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            if (! is_dir($path) || ! is_writable($path)) {
                $ok = false;
                $this->components->warn($folder . ' is not writable');
            }
        }
        $this->components->twoColumnDetail('Folders', $ok ? '<info>writable</info>' : '<comment>see above</comment>');

        return $ok;
    }

    private function administrator(): bool
    {
        if (User::where('role', 'root')->exists()) {
            $this->components->twoColumnDetail('Administrator', 'already there');

            return true;
        }

        $name = $this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Name of the administrator') : null);
        $email = $this->option('email') ?: ($this->input->isInteractive() ? $this->ask('E-mail of the administrator') : null);
        $password = $this->option('password');
        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('Password (at least 8 characters)');
            if ($password !== $this->secret('Repeat the password')) {
                $this->components->error('The two passwords are not the same.');

                return false;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:8']
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }
            $this->line('  Run again with --name, --email and --password, or without them to be asked.');

            return false;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;   // hashed by the model
        $user->role = 'root';
        $user->category = 0;
        $user->email_verified_at = now();
        $user->save();

        $this->components->twoColumnDetail('Administrator', $email . ' (root)');

        return true;
    }
}
