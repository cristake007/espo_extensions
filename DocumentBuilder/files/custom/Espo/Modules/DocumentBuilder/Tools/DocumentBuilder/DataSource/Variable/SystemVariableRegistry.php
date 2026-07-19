<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

final class SystemVariableRegistry
{
    private const NAME_LIST = [
        'currentDate',
        'currentDateTime',
        'currentUserName',
        'pageNumber',
        'pageCount',
    ];

    public function has(string $name): bool
    {
        return in_array($name, self::NAME_LIST, true);
    }
}
