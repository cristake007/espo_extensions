<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Entities\User;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableKind;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;

final readonly class SystemPreviewResolver
{
    public function __construct(
        private SystemVariableResolver $variables,
        private User $user,
    ) {}

    /** @param array<string, mixed> $layout @return list<PreviewValue> */
    public function resolve(array $layout): array
    {
        $identities = [];
        $this->collectIdentities($layout, $identities);
        $defaults = is_array($layout['document']['defaults'] ?? null) ? $layout['document']['defaults'] : [];
        $timezone = is_string($defaults['timezone'] ?? null) ? $defaults['timezone'] : 'UTC';
        $userName = $this->user->get('name');
        $userName = is_string($userName) && $userName !== '' ? $userName : null;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $values = [];

        foreach ($identities as $identity) {
            $result = $this->variables->resolve($identity->path->segments()[0], $now, $userName, $timezone);

            if ($result->kind !== SystemVariableKind::Value || $result->value === null) {
                continue;
            }

            $values[] = new PreviewValue($identity, $result->value, PreviewValueOrigin::System);
        }

        return $values;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<string, VariableIdentity> $identities
     */
    private function collectIdentities(array $value, array &$identities): void
    {
        $candidate = $value['identity'] ?? null;

        if (is_array($candidate) &&
            ($candidate['source'] ?? null) === 'system' &&
            ($candidate['type'] ?? null) === 'system') {
            $identity = VariableIdentity::fromArray($candidate);
            $identities[json_encode($identity->toArray(), JSON_THROW_ON_ERROR)] = $identity;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $this->collectIdentities($item, $identities);
            }
        }
    }
}
