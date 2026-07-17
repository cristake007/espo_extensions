<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use Closure;

interface HolidayStore
{
    /** @param Closure(): ReconciliationResult $operation */
    public function transactional(Closure $operation): ReconciliationResult;

    /** @param list<int> $years @return list<StoredHoliday> */
    public function findManagedScope(string $countryCode, array $years): array;

    public function create(HolidayWriteData $data): void;

    public function update(string $id, HolidayWriteData $data): void;

    public function remove(string $id): void;
}
