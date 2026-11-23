<?php

namespace Database\Seeders;

use App\Analysis\AnalysisCreator;
use App\Analysis\SourceIntake;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Optional demo content: php artisan db:seed --class=DemoSeeder
 *
 * Creates a demo account and one analysis of a deliberately vulnerable sample file
 * (database/seeders/samples). The scanners really run on that file, so the findings are real
 * findings about sample code. The analysis is flagged is_demo and labelled "Demo data" in the UI.
 * Never run this against a database that holds real users.
 */
class DemoSeeder extends Seeder
{
    public function run(SourceIntake $intake, AnalysisCreator $creator): void
    {
        $password = env('DEMO_PASSWORD') ?: Str::password(16, symbols: false);
        $user = User::updateOrCreate(['email' => 'demo@codesentinel.test'], ['name' => 'Demo account', 'password' => Hash::make($password), 'email_verified_at' => now()]);

        if ($user->analyses()->where('is_demo', true)->doesntExist()) {
            // Run in this process, so seeding works without a queue worker.
            config(['queue.default' => 'sync']);
            $analysis = $creator->create($user, 'Demo: vulnerable shop sample', 'demo', [
                $intake->accept('vulnerable_shop.py', (string) file_get_contents(__DIR__.'/samples/vulnerable_shop.py')),
            ]);
            $analysis->forceFill(['is_demo' => true])->save();
        }

        $this->command?->info('Demo account: demo@codesentinel.test');
        $this->command?->info(env('DEMO_PASSWORD') ? 'Password: the value of DEMO_PASSWORD.' : "Password (shown once): {$password}");
    }
}
