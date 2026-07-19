<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use DateTimeImmutable;

interface PublicationActor
{
    public function id(): string;

    public function publishedAt(): DateTimeImmutable;
}
