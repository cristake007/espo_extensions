<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

final readonly class OrmTemplateLifecycleStore implements TemplateLifecycleStore
{
    private const TEMPLATE = 'DocumentBuilderTemplate';
    private const VERSION = 'DocumentBuilderTemplateVersion';

    public function __construct(private EntityManager $entityManager)
    {}

    public function duplicateLocked(string $templateId, callable $duplicator): TemplateLifecycleResult
    {
        return $this->entityManager->getTransactionManager()->run(function () use (
            $templateId,
            $duplicator,
        ): TemplateLifecycleResult {
            $source = $this->lockedTemplate($templateId);
            $data = $duplicator($source, $this->teamIds($source));

            if (!$data instanceof TemplateDuplicateData) {
                throw new RuntimeException('The template duplicator returned an invalid value.');
            }

            $copy = $this->entityManager->getNewEntity(self::TEMPLATE);
            $copy->setMultiple($data->attributes);
            $this->entityManager->saveEntity($copy);

            foreach ($data->teamIds as $teamId) {
                $this->entityManager->getRelation($copy, 'teams')->relateById($teamId);
            }

            $copyId = $copy->getId();

            if ($copyId === null || $copyId === '') {
                throw new RuntimeException('The duplicated template was not assigned an ID.');
            }

            return new TemplateLifecycleResult('duplicate', $copyId, 'Draft', 0);
        });
    }

    public function updateLocked(string $templateId, callable $updater): TemplateLifecycleResult
    {
        return $this->entityManager->getTransactionManager()->run(function () use (
            $templateId,
            $updater,
        ): TemplateLifecycleResult {
            $template = $this->lockedTemplate($templateId);
            $result = $updater($template);
            $this->entityManager->saveEntity($template);

            return $result;
        });
    }

    public function restoreLocked(
        string $templateId,
        string $versionId,
        callable $templateAuthorizer,
        callable $restorer,
    ): TemplateLifecycleResult {
        return $this->entityManager->getTransactionManager()->run(function () use (
            $templateId,
            $versionId,
            $templateAuthorizer,
            $restorer,
        ): TemplateLifecycleResult {
            $template = $this->lockedTemplate($templateId);
            $templateAuthorizer($template);
            $version = $this->entityManager
                ->getRDBRepository(self::VERSION)
                ->where([
                    'id' => $versionId,
                    'templateId' => $templateId,
                ])
                ->findOne();

            if ($version === null) {
                throw new NotFound('Document Builder template version not found.');
            }

            $result = $restorer($template, $version);
            $this->entityManager->saveEntity($template);

            return $result;
        });
    }

    private function lockedTemplate(string $templateId): Entity
    {
        $template = $this->entityManager
            ->getRDBRepository(self::TEMPLATE)
            ->where(['id' => $templateId])
            ->forUpdate()
            ->findOne();

        if ($template === null) {
            throw new NotFound('Document Builder template not found.');
        }

        return $template;
    }

    /** @return list<string> */
    private function teamIds(Entity $template): array
    {
        $ids = [];

        foreach ($this->entityManager->getRelation($template, 'teams')->find() as $team) {
            $id = $team->getId();

            if ($id !== null && $id !== '') {
                $ids[] = $id;
            }
        }

        sort($ids, SORT_STRING);

        return array_values(array_unique($ids));
    }
}
