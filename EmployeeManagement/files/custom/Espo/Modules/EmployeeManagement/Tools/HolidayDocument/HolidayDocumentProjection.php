<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use DateTimeImmutable;
use RuntimeException;

/**
 * Builds presentation-only balances for monthly approval documents.
 *
 * No profile, ledger or request state is changed here. All inputs are captured
 * in the immutable approval snapshot before the approval worker is scheduled.
 */
final class HolidayDocumentProjection
{
    /**
     * @param list<array{dateStart: string, dateEnd: string, year: int, month: int, days: int}> $segments
     * @return list<array<string, float|int|string|bool>>
     */
    public function build(
        array $segments,
        float $annualEntitlement,
        float $balanceBeforeRequest,
        ?string $nextResetDate,
        bool $automaticAnnualGrantEnabled,
    ): array {
        if ($annualEntitlement < 0.0) {
            throw new RuntimeException('The historical annual entitlement is invalid.');
        }

        $resetDate = $this->date($nextResetDate);
        $balance = $balanceBeforeRequest;
        $carryover = max(0.0, $balance - $annualEntitlement);
        $entitlementRemaining = $balance - $carryover;
        $result = [];

        foreach ($segments as $segment) {
            $start = new DateTimeImmutable($segment['dateStart']);
            $end = new DateTimeImmutable($segment['dateEnd']);
            $grantApplied = false;

            while ($automaticAnnualGrantEnabled && $resetDate && $resetDate <= $start) {
                $carryover = $balance;
                $entitlementRemaining = $annualEntitlement;
                $balance += $annualEntitlement;
                $grantApplied = true;
                $resetDate = $resetDate->modify('+1 year');
            }

            if ($automaticAnnualGrantEnabled && $resetDate && $resetDate <= $end) {
                throw new RuntimeException(
                    'A scheduled annual grant falls inside a monthly leave segment; the historical projection is ambiguous.'
                );
            }

            $requested = (float) $segment['days'];
            $shownCarryover = $grantApplied || $carryover > 0.0;
            $daysAlreadyUsed = max(0.0, $annualEntitlement - max(0.0, $entitlementRemaining));

            $result[] = $segment + [
                'annualEntitlement' => $annualEntitlement,
                'carryover' => $carryover,
                'daysAlreadyUsed' => $daysAlreadyUsed,
                'balanceBefore' => $balance,
                'balanceAfter' => $balance - $requested,
                'showsCarryover' => $shownCarryover,
                'nextResetDate' => $resetDate?->format('Y-m-d') ?? '',
            ];

            // The reset implementation adds entitlement to the complete prior
            // balance. For presentation, consume carryover before current-year
            // entitlement so 21 + 3 - 3 is shown as 21, as accounting implies.
            $fromCarryover = min(max(0.0, $carryover), $requested);
            $carryover -= $fromCarryover;
            $entitlementRemaining -= $requested - $fromCarryover;
            $balance -= $requested;
        }

        return $result;
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('The historical annual reset date is invalid.');
        }

        return $date;
    }
}
