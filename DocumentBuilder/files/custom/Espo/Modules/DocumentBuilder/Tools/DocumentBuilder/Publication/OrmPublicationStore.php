<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Core\Exceptions\NotFound;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshot;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

final readonly class OrmPublicationStore implements PublicationStore
{
    private const TEMPLATE = 'DocumentBuilderTemplate';
    private const VERSION = 'DocumentBuilderTemplateVersion';

    public function __construct(private EntityManager $entityManager)
    {}

    public function publishLocked(string $templateId, callable $snapshotFactory): PublicationResult
    {
        return $this->entityManager->getTransactionManager()->run(
            function () use ($templateId, $snapshotFactory): PublicationResult {
                $template = $this->entityManager
                    ->getRDBRepository(self::TEMPLATE)
                    ->where(['id' => $templateId])
                    ->forUpdate()
                    ->findOne();

                if ($template === null) {
                    throw new NotFound('Document Builder template not found.');
                }

                $nextVersionNumber = $this->nextVersionNumber($templateId);
                $teamIds = $this->teamIds($template);
                $snapshot = $snapshotFactory($template, $nextVersionNumber, $teamIds);

                if (!$snapshot instanceof TemplateVersionSnapshot) {
                    throw new RuntimeException('The publication snapshot factory returned an invalid value.');
                }

                foreach ($this->currentVersions($templateId) as $currentVersion) {
                    $currentVersion->set('isCurrent', false);
                    $this->entityManager->saveEntity($currentVersion);
                }

                $attributes = $snapshot->attributes();
                $versionTeamIds = $attributes['teamsIds'] ?? [];
                unset($attributes['teamsIds']);

                $version = $this->entityManager->getNewEntity(self::VERSION);
                $version->setMultiple($attributes);
                $this->entityManager->saveEntity($version);

                foreach ($versionTeamIds as $teamId) {
                    $this->entityManager->getRelation($version, 'teams')->relateById($teamId);
                }

                $versionId = $version->getId();

                if ($versionId === null || $versionId === '') {
                    throw new RuntimeException('The published version was not assigned an ID.');
                }

                $template->setMultiple([
                    'status' => 'Published',
                    'currentPublishedVersionId' => $versionId,
                    'isActive' => true,
                ]);
                $this->entityManager->saveEntity($template);

                $publishedAt = $attributes['publishedAt'] ?? null;

                if (!is_string($publishedAt)) {
                    throw new RuntimeException('The publication snapshot has no publication timestamp.');
                }

                return new PublicationResult(
                    $templateId,
                    $versionId,
                    $nextVersionNumber,
                    $snapshot->checksum(),
                    $publishedAt,
                );
            },
        );
    }

    private function nextVersionNumber(string $templateId): int
    {
        $latest = $this->entityManager
            ->getRDBRepository(self::VERSION)
            ->where(['templateId' => $templateId])
            ->order('versionNumber', 'DESC')
            ->findOne();

        if ($latest === null) {
            return 1;
        }

        $versionNumber = $latest->get('versionNumber');

        if (!is_int($versionNumber) || $versionNumber < 1) {
            throw new RuntimeException('The latest template version number is invalid.');
        }

        return $versionNumber + 1;
    }

    /** @return iterable<Entity> */
    private function currentVersions(string $templateId): iterable
    {
        return $this->entityManager
            ->getRDBRepository(self::VERSION)
            ->where([
                'templateId' => $templateId,
                'isCurrent' => true,
            ])
            ->find();
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
