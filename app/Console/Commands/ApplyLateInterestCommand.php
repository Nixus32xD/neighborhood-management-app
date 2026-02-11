<?php

namespace App\Console\Commands;

use App\Models\UnitExpense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLateInterestCommand extends Command
{
    protected $signature = 'expenses:apply-late-interest
                            {--day= : Day of month to apply interest (11 for monthly, 21 for extraordinary)}
                            {--period= : Period in Y-m format. Defaults to current period}';

    protected $description = 'Apply 10% late interest to unpaid expenses based on due dates';

    public function handle(): int
    {
        $day = (int) ($this->option('day') ?: now()->day);
        $period = $this->option('period') ?: now()->format('Y-m');

        if (!in_array($day, [11, 21], true)) {
            $this->error('Invalid day. Use 11 (monthly) or 21 (extraordinary).');
            return self::FAILURE;
        }

        $affected = 0;
        $totalInterest = 0.0;
        $today = now()->toDateString();

        UnitExpense::where('period', $period)
            ->orderBy('id')
            ->chunkById(200, function ($expenses) use ($day, $today, &$affected, &$totalInterest) {
                foreach ($expenses as $expense) {
                    $paidAmount = (float) $expense->paid_amount;
                    $monthlyAmount = (float) $expense->monthly_amount;
                    $extraordinaryAmount = (float) $expense->extraordinary_amount;

                    if ($day === 11) {
                        if ($expense->monthly_interest_applied_at !== null) {
                            continue;
                        }

                        $base = max(0, $monthlyAmount - $paidAmount);
                        if ($base <= 0) {
                            continue;
                        }

                        $interest = round($base * 0.10, 2);
                        if ($interest <= 0) {
                            continue;
                        }

                        $interestLiteral = number_format($interest, 2, '.', '');

                        $updated = UnitExpense::whereKey($expense->id)
                            ->whereNull('monthly_interest_applied_at')
                            ->update([
                                'fines_amount' => DB::raw("fines_amount + {$interestLiteral}"),
                                'monthly_interest_applied_at' => $today,
                            ]);

                        if ($updated > 0) {
                            $affected++;
                            $totalInterest += $interest;
                        }
                    }

                    if ($day === 21) {
                        if ($expense->extraordinary_interest_applied_at !== null) {
                            continue;
                        }

                        $paidAfterMonthly = max(0, $paidAmount - $monthlyAmount);
                        $base = max(0, $extraordinaryAmount - $paidAfterMonthly);

                        if ($base <= 0) {
                            continue;
                        }

                        $interest = round($base * 0.10, 2);
                        if ($interest <= 0) {
                            continue;
                        }

                        $interestLiteral = number_format($interest, 2, '.', '');

                        $updated = UnitExpense::whereKey($expense->id)
                            ->whereNull('extraordinary_interest_applied_at')
                            ->update([
                                'fines_amount' => DB::raw("fines_amount + {$interestLiteral}"),
                                'extraordinary_interest_applied_at' => $today,
                            ]);

                        if ($updated > 0) {
                            $affected++;
                            $totalInterest += $interest;
                        }
                    }
                }
            });

        $this->info("Late interest applied for day {$day}, period {$period}.");
        $this->info("Updated expenses: {$affected}");
        $this->info('Total interest added: ' . number_format($totalInterest, 2, '.', ''));

        return self::SUCCESS;
    }
}

