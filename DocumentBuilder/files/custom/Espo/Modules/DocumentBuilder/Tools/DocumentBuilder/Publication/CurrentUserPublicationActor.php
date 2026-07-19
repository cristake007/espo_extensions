<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Entities\User;
use RuntimeException;

final readonly class CurrentUserPublicationActor implements PublicationActor
{
    public function __construct(private User $user)
    {}

    public function id(): string
    {
        $id = $this->user->getId();

        if ($id === null || $id === '') {
            throw new RuntimeException('A publication actor ID is required.');
        }

        return $id;
    }

    public function publishedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
