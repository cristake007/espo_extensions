<?php

declare(strict_types=1);

namespace Espo\Core\Exceptions;

class BadRequest extends \RuntimeException
{
}

class Forbidden extends \RuntimeException
{
}

class NotFound extends \RuntimeException
{
}

namespace Espo\Core\Acl;

class Table
{
    public const ACTION_READ = 'read';
    public const ACTION_EDIT = 'edit';
}

namespace Espo\Core;

use Espo\Entities\Attachment;

class Acl
{
    public bool $scopeRead = true;
    public bool $scopeEdit = true;
    public bool $recordRead = true;
    public bool $recordEdit = true;
    public bool $attachmentRead = true;
    public array $scopeChecks = [];

    public function checkScope(string $entityType, string $action): bool
    {
        $this->scopeChecks[] = [$entityType, $action];

        return $action === 'read' ? $this->scopeRead : $this->scopeEdit;
    }

    public function checkEntityRead(object $entity): bool
    {
        return $entity instanceof Attachment ? $this->attachmentRead : $this->recordRead;
    }

    public function checkEntityEdit(object $entity): bool
    {
        return $this->recordEdit;
    }
}

namespace Espo\Core\FileStorage;

use Espo\Entities\Attachment;

class Manager
{
    public array $contents = [];
    public array $reads = [];

    public function getContents(Attachment $attachment): string
    {
        $this->reads[] = $attachment->getId();

        return $this->contents[$attachment->getId()] ?? '';
    }
}

namespace Espo\Core\ORM;

class EntityManager
{
    public array $entities = [];
    public array $saved = [];

    public function getEntityById(string $entityType, string $id): ?object
    {
        return $this->entities[$entityType . ':' . $id] ?? null;
    }

    public function saveEntity(object $entity): void
    {
        $this->saved[] = $entity;
    }
}

namespace Espo\Core\Utils;

class Language
{
    public function translateLabel(string $key, string $category, string $scope): string
    {
        return $key;
    }
}

class Log
{
    public array $infoEntries = [];
    public array $errorEntries = [];

    public function info(string $message, array $context = []): void
    {
        $this->infoEntries[] = [$message, $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->errorEntries[] = [$message, $context];
    }
}

namespace Espo\Entities;

class Attachment
{
    public const ENTITY_TYPE = 'Attachment';

    public function __construct(
        private string $id,
        private string $name,
        private string $relatedType,
        private string $relatedId,
        private string $targetField
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRelatedType(): string
    {
        return $this->relatedType;
    }

    public function getTargetField(): string
    {
        return $this->targetField;
    }

    public function get(string $field): mixed
    {
        return $field === 'relatedId' ? $this->relatedId : null;
    }
}

namespace WordPressUpdaterTest;

class Record
{
    public function __construct(private string $id, public array $attributes)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(string $field): mixed
    {
        return $this->attributes[$field] ?? null;
    }

    public function set(string $field, mixed $value): self
    {
        $this->attributes[$field] = $value;

        return $this;
    }
}
