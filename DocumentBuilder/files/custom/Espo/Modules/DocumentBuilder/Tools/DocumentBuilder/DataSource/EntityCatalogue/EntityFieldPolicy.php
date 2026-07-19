<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final class EntityFieldPolicy
{
    private const SUPPORTED_TYPE_LIST = [
        'address', 'array', 'bool', 'currency', 'date', 'datetime', 'duration',
        'email', 'enum', 'float', 'int', 'multiEnum', 'number', 'percent',
        'personName', 'phone', 'text', 'url', 'varchar',
    ];

    private const TECHNICAL_FIELD_LIST = [
        'createdAt', 'createdBy', 'createdById', 'deleted', 'id', 'modifiedAt',
        'modifiedBy', 'modifiedById', 'password', 'teamsIds', 'userName',
    ];

    /** @param array<string, mixed> $definition */
    public function allows(string $field, array $definition): bool
    {
        $type = $definition['type'] ?? null;

        return preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $field) === 1 &&
            is_string($type) &&
            in_array($type, self::SUPPORTED_TYPE_LIST, true) &&
            !in_array($field, self::TECHNICAL_FIELD_LIST, true) &&
            preg_match('/(?:password|passwd|token|secret|api[_-]?key|auth|hash|salt)/i', $field) !== 1 &&
            ($definition['documentBuilderDisabled'] ?? false) !== true &&
            ($definition['disabled'] ?? false) !== true &&
            ($definition['isInternal'] ?? false) !== true;
    }
}
