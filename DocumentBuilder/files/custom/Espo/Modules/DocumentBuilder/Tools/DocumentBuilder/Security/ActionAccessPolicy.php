<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\ORM\Entity;
use InvalidArgumentException;

final readonly class ActionAccessPolicy
{
    public function __construct(private Acl $acl)
    {}

    public function requireAction(ActionPermission $permission): void
    {
        if ($this->acl->getPermissionLevel($permission->value) !== Table::LEVEL_YES) {
            throw new PermissionDenied();
        }
    }

    public function requireRecordRead(ActionPermission $permission, Entity $record): void
    {
        $this->requireAction($permission);
        $this->requireReadableRecord($record);
    }

    public function requireRecordEdit(ActionPermission $permission, Entity $record): void
    {
        $this->requireAction($permission);

        if (
            !$this->acl->checkScope($record->getEntityType(), Table::ACTION_EDIT) ||
            !$this->acl->checkEntity($record, Table::ACTION_EDIT)
        ) {
            throw new PermissionDenied();
        }
    }

    /**
     * @param list<FieldReadRequirement> $fieldRequirements
     * @param list<LinkReadRequirement> $linkRequirements
     */
    public function requireSourceRead(
        ActionPermission $permission,
        Entity $source,
        array $fieldRequirements = [],
        array $linkRequirements = [],
    ): void {
        $this->requireAction($permission);
        $this->requireReadableRecord($source);

        foreach ($fieldRequirements as $requirement) {
            if (!$requirement instanceof FieldReadRequirement) {
                throw new InvalidArgumentException('A field requirement has an invalid type.');
            }

            if (
                !$this->acl->checkScope($requirement->scope, Table::ACTION_READ) ||
                !$this->acl->checkField($requirement->scope, $requirement->field, Table::ACTION_READ)
            ) {
                throw new PermissionDenied();
            }
        }

        foreach ($linkRequirements as $requirement) {
            if (!$requirement instanceof LinkReadRequirement) {
                throw new InvalidArgumentException('A link requirement has an invalid type.');
            }

            if (
                !$this->acl->checkScope($requirement->scope, Table::ACTION_READ) ||
                !$this->acl->checkLink($requirement->scope, $requirement->link, Table::ACTION_READ)
            ) {
                throw new PermissionDenied();
            }
        }
    }

    /**
     * Inaccessible collection records are removed silently. Callers must not add a
     * warning or count that reveals the filtered records.
     *
     * @param iterable<Entity> $records
     * @return list<Entity>
     */
    public function filterReadableRelatedRecords(
        ActionPermission $permission,
        iterable $records,
    ): array {
        $this->requireAction($permission);
        $readable = [];

        foreach ($records as $record) {
            if (!$record instanceof Entity) {
                throw new InvalidArgumentException('A related record has an invalid type.');
            }

            if ($this->canReadRecord($record)) {
                $readable[] = $record;
            }
        }

        return $readable;
    }

    private function requireReadableRecord(Entity $record): void
    {
        if (!$this->canReadRecord($record)) {
            throw new PermissionDenied();
        }
    }

    private function canReadRecord(Entity $record): bool
    {
        return $this->acl->checkScope($record->getEntityType(), Table::ACTION_READ) &&
            $this->acl->checkEntity($record, Table::ACTION_READ);
    }
}
