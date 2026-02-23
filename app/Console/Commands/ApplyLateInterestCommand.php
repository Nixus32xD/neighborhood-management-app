<?php

namespace App\Console\Commands;

use App\Models\UnitExpense;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLateInterestCommand extends Command
{
    protected $signature = 'expenses:apply-late-interest
                            {--day= : Day of month to apply interest (10, 15, or 20)}
                            {--period= : Period in Y-m format. Defaults to current period}';

    protected $description = 'Apply late interest to unpaid expenses by neighborhood rules';

    public function handle(): int
    {
        $day = (int) ($this->option('day') ?: now()->day);
        $period = $this->option('period');
        $today = now();
        $todayString = $today->toDateString();
        $currentPeriod = $today->format('Y-m');

        if (!in_array($day, [10, 15, 20], true)) {
            $this->error('Invalid day. Use 10 (CC1 monthly), 15 (CC2), or 20 (CC1 extraordinary).');
            return self::FAILURE;
        }

        $affected = 0;
        $totalInterest = 0.0;

        $query = UnitExpense::query()
            ->with('unit.neighborhood')
            ->orderBy('id')
            ->when(
                $period,
                fn ($q) => $q->where('period', $period),
                fn ($q) => $q->where('period', '<=', $currentPeriod)
            );

        $query->chunkById(200, function ($expenses) use ($day, $today, $todayString, &$affected, &$totalInterest) {
                foreach ($expenses as $expense) {
                    $paidAmount = (float) $expense->paid_amount;
                    $monthlyAmount = (float) $expense->monthly_amount;
                    $extraordinaryAmount = (float) $expense->extraordinary_amount;
                    $rate = $this->resolveInterestRate($expense, $today);

                    $neighborhoodName = strtoupper(trim((string) data_get($expense, 'unit.neighborhood.name', '')));

                    // CC1: monthly late interest on day 10.
                    if ($neighborhoodName === 'CC1' && $day === 10) {
                        if ($this->alreadyAppliedThisMonth($expense->monthly_interest_applied_at, $today)) {
                            continue;
                        }

                        $base = max(0, $monthlyAmount - $paidAmount);
                        if ($base <= 0) {
                            continue;
                        }

                        $interest = round($base * $rate, 2);
                        if ($interest <= 0) {
                            continue;
                        }

                        $interestLiteral = number_format($interest, 2, '.', '');

                        $updated = UnitExpense::whereKey($expense->id)
                            ->update([
                                'fines_amount' => DB::raw("fines_amount + {$interestLiteral}"),
                                'monthly_interest_applied_at' => $todayString,
                            ]);

                        if ($updated > 0) {
                            $affected++;
                            $totalInterest += $interest;
                        }
                    }

                    // CC1: extraordinary late interest on day 20.
                    if ($neighborhoodName === 'CC1' && $day === 20) {
                        if ($this->alreadyAppliedThisMonth($expense->extraordinary_interest_applied_at, $today)) {
                            continue;
                        }

                        $paidAfterMonthly = max(0, $paidAmount - $monthlyAmount);
                        $base = max(0, $extraordinaryAmount - $paidAfterMonthly);

                        if ($base <= 0) {
                            continue;
                        }

                        $interest = round($base * $rate, 2);
                        if ($interest <= 0) {
                            continue;
                        }

                        $interestLiteral = number_format($interest, 2, '.', '');

                        $updated = UnitExpense::whereKey($expense->id)
                            ->update([
                                'fines_amount' => DB::raw("fines_amount + {$interestLiteral}"),
                                'extraordinary_interest_applied_at' => $todayString,
                            ]);

                        if ($updated > 0) {
                            $affected++;
                            $totalInterest += $interest;
                        }
                    }

                    // CC2: one monthly application on day 15 over total outstanding.
                    if ($neighborhoodName === 'CC2' && $day === 15) {
                        if (
                            $this->alreadyAppliedThisMonth($expense->monthly_interest_applied_at, $today) ||
                            $this->alreadyAppliedThisMonth($expense->extraordinary_interest_applied_at, $today)
                        ) {
                            continue;
                        }

                        $base = max(0, ($monthlyAmount + $extraordinaryAmount) - $paidAmount);
                        if ($base <= 0) {
                            continue;
                        }

                        $interest = round($base * $rate, 2);
                        if ($interest <= 0) {
                            continue;
                        }

                        $interestLiteral = number_format($interest, 2, '.', '');

                        $updated = UnitExpense::whereKey($expense->id)
                            ->update([
                                'fines_amount' => DB::raw("fines_amount + {$interestLiteral}"),
                                // Mark both to prevent same-month re-application in manual runs.
                                'monthly_interest_applied_at' => $todayString,
                                'extraordinary_interest_applied_at' => $todayString,
                            ]);

                        if ($updated > 0) {
                            $affected++;
                            $totalInterest += $interest;
                        }
                    }
                }
            });

        $scope = $period ?: "up to {$currentPeriod}";
        $this->info("Late interest applied for day {$day}, period scope {$scope}.");
        $this->info("Updated expenses: {$affected}");
        $this->info('Total interest added: ' . number_format($totalInterest, 2, '.', ''));

        return self::SUCCESS;
    }

    private function resolveInterestRate(UnitExpense $expense, Carbon $today): float
    {
        $neighborhoodName = strtoupper(trim((string) data_get($expense, 'unit.neighborhood.name', '')));

        // CC1 keeps the current behavior.
        if ($neighborhoodName !== 'CC2') {
            return 0.10;
        }

        $baseRate = (float) config('fines.cc2_construction_index_rate', 0.03);
        $monthsOverdue = $this->monthsOverdue($expense->period, $today);

        // CC2: month 1-2 uses index rate, month 3+ doubles the index.
        return $monthsOverdue >= 3 ? $baseRate * 2 : $baseRate;
    }

    private function monthsOverdue(string $period, Carbon $today): int
    {
        try {
            $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        } catch (\Throwable) {
            return 1;
        }

        $currentDate = $today->copy()->startOfMonth();
        $diffMonths = (int) $periodDate->diffInMonths($currentDate, false);

        if ($diffMonths < 0) {
            return 1;
        }

        return $diffMonths + 1;
    }

    private function alreadyAppliedThisMonth(?Carbon $appliedAt, Carbon $today): bool
    {
        return $appliedAt !== null && $appliedAt->format('Y-m') === $today->format('Y-m');
    }
}
