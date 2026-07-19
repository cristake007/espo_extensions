<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;

final class ConfiguredEntitySourcePolicy implements EntitySourcePolicy
{
    private EntitySourcePolicyRules $rules;

    public function __construct(ConfigProvider $configProvider)
    {
        $settings = $configProvider->get();
        $this->rules = new EntitySourcePolicyRules(
            $settings->enabledSourceEntityTypeList(),
            $settings->disabledSourceEntityTypeList(),
        );
    }

    public function allows(string $entityType): bool
    {
        return $this->rules->allows($entityType);
    }
}
