<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderDocument;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\DocumentHistoryPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\DocumentStatus;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\InvalidDocumentMutation;
use Espo\ORM\Entity;
use InvalidArgumentException;

final readonly class BeforeUpdate implements UpdateHook
{
    public function __construct(private DocumentHistoryPolicy $policy)
    {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        $changedFields = array_values(array_filter(
            DocumentHistoryPolicy::IMMUTABLE_AFTER_SUCCESS,
            static fn (string $field): bool => $entity->isAttributeChanged($field),
        ));

        try {
            $this->policy->assertUpdate(
                DocumentStatus::fromStored($entity->getFetched('status')),
                DocumentStatus::fromStored($entity->get('status')),
                $changedFields,
            );
        } catch (InvalidArgumentException | InvalidDocumentMutation) {
            throw new Forbidden('Generated-document provenance is immutable after successful generation.');
        }
    }
}
