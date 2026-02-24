<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('expenses:apply-late-interest --day=10')->monthlyOn(11, '00:00')->timezone('America/Argentina/Buenos_Aires');
Schedule::command('expenses:apply-late-interest --day=15')->monthlyOn(16, '00:00')->timezone('America/Argentina/Buenos_Aires');
Schedule::command('expenses:apply-late-interest --day=20')->monthlyOn(21, '00:00')->timezone('America/Argentina/Buenos_Aires');

