<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final readonly class OrmPreviewTemplateStore implements PreviewTemplateStore
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function find(string $templateId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('DocumentBuilderTemplate')
            ->select(['id', 'status', 'revision', 'currentDraftLayout'])
            ->where(['id' => $templateId, 'deleted' => false])
            ->findOne();
    }
}
