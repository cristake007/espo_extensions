<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error;

enum WarningCode: string
{
    case ValueUnavailable = 'valueUnavailable';
    case MediaUnavailable = 'mediaUnavailable';
    case CollectionTruncated = 'collectionTruncated';
    case RendererCompatibility = 'rendererCompatibility';

    public function messageKey(): string
    {
        return 'warnings.' . $this->value;
    }
}
