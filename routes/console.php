<?php

use App\Console\Commands\App\Vehicle\OneHtmlFetch;
use App\Console\Commands\App\Vehicle\OneRefreshCookie;
use App\Console\Commands\App\Vehicle\OneVehiclesImport;
use App\Console\Commands\App\Vehicle\OneViolationsImport;
use App\Console\Commands\App\Vehicle\VehicleViolationUsagesIdUpdate;
use App\Console\Commands\Sys\SmtpSelfTest;
use Illuminate\Support\Facades\Schedule;

Schedule::command(OneRefreshCookie::class)->everyFifteenMinutes()->withoutOverlapping();

Schedule::command(VehicleViolationUsagesIdUpdate::class)->everyTenMinutes();

Schedule::command(SmtpSelfTest::class)->dailyAt('09:00')->description('SMTP 每日自检邮件');

Schedule::call(function () {
    $commands = [OneRefreshCookie::class, OneHtmlFetch::class, OneVehiclesImport::class, OneViolationsImport::class];

    foreach ($commands as $command) {
        $exitCode = $this->call($command);
        $output   = $this->output();

        if (0 !== $exitCode) {
            break;
        }
    }
})->cron('0 8 * * *')->description('每日早8点1轮122');
