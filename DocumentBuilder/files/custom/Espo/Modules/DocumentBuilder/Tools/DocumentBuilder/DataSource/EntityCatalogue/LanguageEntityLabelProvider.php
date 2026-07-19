<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Core\Utils\Language;

final readonly class LanguageEntityLabelProvider implements EntityLabelProvider
{
    public function __construct(private Language $language)
    {}

    public function label(string $entityType): string
    {
        return $this->language->translateLabel($entityType, 'scopeNames');
    }

    public function fieldLabel(string $entityType, string $field): string
    {
        return $this->language->translateLabel($field, 'fields', $entityType);
    }

    public function linkLabel(string $entityType, string $link): string
    {
        return $this->language->translateLabel($link, 'links', $entityType);
    }
}
