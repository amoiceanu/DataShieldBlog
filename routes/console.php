<?php

use App\Models\ExposureChallenge;
use App\Models\ExposureReport;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exposure:prune', function () {
    $deleted = ExposureReport::where('expires_at', '<=', now())->delete();
    ExposureChallenge::where('expires_at', '<=', now())->delete();
    ExposureReport::whereIn('state', ['queued', 'processing'])->where('updated_at', '<', now()->subMinutes(20))->update(['state' => 'incomplete']);
    DB::table('exposure_usage')->where('day', '<', today()->subDays(29))->delete();
    $this->info('Rapoarte expirate șterse: '.$deleted);
})->purpose('Șterge rapoartele expirate fără a afișa date personale.');
Schedule::command('exposure:prune')->everyMinute()->withoutOverlapping();
Artisan::command('exposure:admin {id}', function () {
    $user = User::findOrFail($this->argument('id'));
    $user->forceFill(['is_exposure_admin' => true])->save();
    $this->info('Acces administrativ acordat utilizatorului selectat.');
});

Artisan::command('exposure:key', function () {
    $file = app()->environmentFilePath();
    if (! is_file($file)) {
        $this->error('Creează mai întâi fișierul .env din exemplu.');

        return 1;
    }
    $contents = file_get_contents($file);
    if (preg_match('/^EXPOSURE_HMAC_KEY=.+$/m', $contents)) {
        $this->error('Cheia există deja; nu a fost modificată.');

        return 1;
    }
    $line = 'EXPOSURE_HMAC_KEY='.bin2hex(random_bytes(32));
    $contents = preg_match('/^EXPOSURE_HMAC_KEY=.*$/m', $contents) ? preg_replace('/^EXPOSURE_HMAC_KEY=.*$/m', $line, $contents) : $contents."\n".$line."\n";
    file_put_contents($file, $contents, LOCK_EX);
    $this->info('Cheia dedicată a fost salvată în .env fără afișare. Reîncarcă configurația și workerii.');
})->purpose('Generează cheia HMAC dedicată fără a o afișa sau suprascrie.');
