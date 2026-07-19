<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class SystemVariableResolver
{
    public function __construct(private SystemVariableRegistry $registry)
    {}

    public function resolve(
        string $name,
        DateTimeImmutable $now,
        ?string $currentUserName,
        string $timezone = 'UTC',
    ): SystemVariableResult {
        if (!$this->registry->has($name)) {
            throw new InvalidArgumentException('A system variable is unsupported.');
        }

        try {
            $timezoneObject = new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('A system-variable timezone is invalid.');
        }

        $utc = $now->setTimezone(new DateTimeZone('UTC'));

        return match ($name) {
            'currentDate' => SystemVariableResult::value(new VariableValue(
                VariableValueType::Date,
                VariableValueState::Present,
                $now->setTimezone($timezoneObject)->format('Y-m-d'),
            )),
            'currentDateTime' => SystemVariableResult::value(new VariableValue(
                VariableValueType::DateTime,
                VariableValueState::Present,
                $utc->format('Y-m-d\TH:i:sP'),
            )),
            'currentUserName' => SystemVariableResult::value(new VariableValue(
                VariableValueType::Text,
                $currentUserName === null ? VariableValueState::Missing : VariableValueState::Present,
                $currentUserName,
            )),
            'pageNumber' => SystemVariableResult::placeholder('pageNumber'),
            'pageCount' => SystemVariableResult::placeholder('pageCount'),
        };
    }
}
