<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monitoring website OPD.
Schedule::command('simonta:dispatch-scheduled-scans')
    ->everyMinute()
    ->withoutOverlapping(5);

// Pemulihan scan yang terlalu lama berstatus running.
Schedule::command('simonta:recover-stale-scans')
    ->everyMinute()
    ->withoutOverlapping(5);

// Pemeriksaan registrasi domain dan sertifikat SSL setiap hari.
Schedule::command('simonta:check-domain-ssl')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60);