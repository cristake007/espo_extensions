<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security;

enum ActionPermission: string
{
    case DesignTemplates = 'documentBuilderDesignTemplates';
    case PublishTemplates = 'documentBuilderPublishTemplates';
    case GenerateDocuments = 'documentBuilderGenerateDocuments';
    case GenerateBatches = 'documentBuilderGenerateBatches';
    case UseSpreadsheetImports = 'documentBuilderUseSpreadsheetImports';
    case ManageSharedMedia = 'documentBuilderManageSharedMedia';
    case ViewDataSnapshots = 'documentBuilderViewDataSnapshots';
    case DeleteGeneratedDocuments = 'documentBuilderDeleteGeneratedDocuments';
    case Configure = 'documentBuilderConfigure';

    public function fieldName(): string
    {
        return $this->value . 'Permission';
    }
}
