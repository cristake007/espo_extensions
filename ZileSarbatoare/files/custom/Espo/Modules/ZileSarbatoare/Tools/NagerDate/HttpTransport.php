<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

interface HttpTransport
{
    /** @throws ClientException */
    public function get(string $url, int $maximumBytes): HttpResponse;
}
