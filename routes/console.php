<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('expenses:apply-late-interest --day=11')->monthlyOn(11, '00:10');
Schedule::command('expenses:apply-late-interest --day=21')->monthlyOn(21, '00:10');
