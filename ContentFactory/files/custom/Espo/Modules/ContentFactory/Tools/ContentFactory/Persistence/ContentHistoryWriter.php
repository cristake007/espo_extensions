<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Entities\User;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationRequest;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\ContractRegistry;
use Espo\ORM\EntityManager;
use Throwable;

final readonly class ContentHistoryWriter
{
    public function __construct(
        private EntityManager $entityManager,
        private User $user,
        private ContractRegistry $registry,
    ) {}

    /**
     * @param array<string, mixed> $generation
     * @return array{id: string, name: string, generatedAt: string}
     */
    public function write(GenerationRequest $request, array $generation): array
    {
        $platform = $this->registry->platform($request->platform);
        $format = $this->registry->format($request->format);
        $style = $this->registry->style($request->style);

        if ($platform === null || $format === null || $style === null) {
            throw new GenerationException(
                'CONFIGURATION_ERROR',
                503,
                'Contractul Content Factory nu este configurat.',
            );
        }

        try {
            $json = json_encode(
                $generation,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s');
            $name = $this->nameFromBrief($request->brief);
            $entity = $this->entityManager->getNewEntity('ContentFactoryContent');
            $entity->set([
                'name' => $name,
                'brief' => $request->brief,
                'platform' => $platform['entityValue'],
                'format' => $format['entityValue'],
                'style' => $style['entityValue'],
                'customInstructions' => $request->customInstructions !== ''
                    ? $request->customInstructions
                    : null,
                'contentType' => $request->contentType,
                'courseId' => $request->course['id'] ?? null,
                'courseName' => $request->course['name'] ?? null,
                'courseSlug' => $request->course['slug'] ?? null,
                'courseUrl' => $request->course['url'] ?? null,
                'generatedContent' => $json,
                'status' => 'Draft',
                'generatedAt' => $generatedAt,
                'assignedUserId' => $this->user->getId(),
            ]);
            $this->entityManager->saveEntity($entity);

            $id = $entity->getId();

            if (!is_string($id) || $id === '') {
                throw new GenerationException(
                    'GENERATION_SAVE_FAILED',
                    500,
                    'Conținutul generat nu a putut fi salvat.',
                );
            }

            return ['id' => $id, 'name' => $name, 'generatedAt' => $generatedAt];
        } catch (GenerationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new GenerationException(
                'GENERATION_SAVE_FAILED',
                500,
                'Conținutul generat nu a putut fi salvat.',
            );
        }
    }

    private function nameFromBrief(string $brief): string
    {
        $line = trim((string) preg_split('/\R/u', $brief, 2)[0]);

        return mb_substr($line !== '' ? $line : 'Conținut generat', 0, 190);
    }
}
