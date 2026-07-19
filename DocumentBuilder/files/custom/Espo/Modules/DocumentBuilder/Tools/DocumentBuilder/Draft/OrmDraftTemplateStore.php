<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;

final readonly class OrmDraftTemplateStore implements DraftTemplateStore
{
    private const ENTITY_TYPE = 'DocumentBuilderTemplate';

    public function __construct(private EntityManager $entityManager)
    {}

    public function updateLocked(string $templateId, callable $updater): mixed
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($templateId, $updater): mixed {
            $template = $this->entityManager
                ->getRDBRepository(self::ENTITY_TYPE)
                ->where(['id' => $templateId])
                ->forUpdate()
                ->findOne();

            if ($template === null) {
                throw new NotFound('Document Builder template not found.');
            }

            $result = $updater($template);
            $this->entityManager->saveEntity($template);

            return $result;
        });
    }
}
