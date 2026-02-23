<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('expenses:apply-late-interest --day=10')->monthlyOn(10, '00:10');
Schedule::command('expenses:apply-late-interest --day=15')->monthlyOn(15, '00:10');
Schedule::command('expenses:apply-late-interest --day=20')->monthlyOn(20, '00:10');
