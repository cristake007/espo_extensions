<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

interface EntityLabelProvider
{
    public function label(string $entityType): string;

    public function fieldLabel(string $entityType, string $field): string;

    public function linkLabel(string $entityType, string $link): string;
}
