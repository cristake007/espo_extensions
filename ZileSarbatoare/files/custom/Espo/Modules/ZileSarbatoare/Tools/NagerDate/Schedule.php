<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;

final class Schedule
{
    public function nextRun(Settings $settings, DateTimeImmutable $after): ?DateTimeImmutable
    {
        if (!$settings->enabled || !$settings->automaticSync || $settings->frequency === 'ManualOnly') {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $settings->timeOfDay));

        if ($settings->frequency === 'Daily') {
            $candidate = $after->setTime($hour, $minute);

            return $candidate <= $after ? $candidate->modify('+1 day') : $candidate;
        }

        if ($settings->frequency === 'Weekly') {
            $daysAhead = ($settings->dayOfWeek - (int) $after->format('N') + 7) % 7;
            $candidate = $after->modify("+$daysAhead days")->setTime($hour, $minute);

            return $candidate <= $after ? $candidate->modify('+7 days') : $candidate;
        }

        $candidate = $this->monthlyCandidate($after, $settings->dayOfMonth, $hour, $minute);

        if ($candidate <= $after) {
            $candidate = $this->monthlyCandidate(
                $after->modify('first day of next month'),
                $settings->dayOfMonth,
                $hour,
                $minute,
            );
        }

        return $candidate;
    }

    public function isDue(
        Settings $settings,
        DateTimeImmutable $now,
        ?DateTimeImmutable $lastAttemptedAt,
    ): bool {
        if (!$settings->enabled || !$settings->automaticSync || $settings->frequency === 'ManualOnly') {
            return false;
        }

        $lookBehind = $settings->frequency === 'Monthly' ? '-32 days' : '-8 days';
        $latestSlot = $this->nextRun($settings, $now->modify($lookBehind));

        while ($latestSlot !== null) {
            $nextSlot = $this->nextRun($settings, $latestSlot);

            if ($nextSlot === null || $nextSlot > $now) {
                break;
            }

            $latestSlot = $nextSlot;
        }

        return $latestSlot !== null && $latestSlot <= $now &&
            ($lastAttemptedAt === null || $lastAttemptedAt < $latestSlot);
    }

    private function monthlyCandidate(
        DateTimeImmutable $month,
        int $requestedDay,
        int $hour,
        int $minute,
    ): DateTimeImmutable {
        $day = min($requestedDay, (int) $month->format('t'));

        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day)
            ->setTime($hour, $minute);
    }
}
