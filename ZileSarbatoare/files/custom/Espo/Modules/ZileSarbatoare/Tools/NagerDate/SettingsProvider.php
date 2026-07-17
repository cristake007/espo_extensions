<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;
use RuntimeException;

final class SettingsProvider
{
    public const INTEGRATION = 'NagerDate';

    public function __construct(
        private EntityManager $entityManager,
        private ApplicationConfig $applicationConfig,
        private SettingsNormalizer $normalizer,
    ) {}

    public function get(?DateTimeImmutable $now = null): Settings
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone($this->applicationConfig->getTimeZone()));

        return $this->normalizer->normalize(get_object_vars($this->getEntity()->getValueMap()), $now);
    }

    public function getEntity(): Integration
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->getById(self::INTEGRATION);

        if (!$entity) {
            throw new RuntimeException('The Nager.Date integration record is unavailable.');
        }

        return $entity;
    }

    public function saveEntity(Integration $entity): void
    {
        $this->entityManager->saveEntity($entity);
    }
}
