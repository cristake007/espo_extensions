<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\AclDraftRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\ConfiguredLayoutProcessorProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftTemplateStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\LayoutProcessorProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\NoopSourceReferenceImpactAnalyzer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\OrmDraftTemplateStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\SourceReferenceImpactAnalyzer;

final class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder
            ->bindImplementation(DraftTemplateStore::class, OrmDraftTemplateStore::class)
            ->bindImplementation(DraftRecordAccess::class, AclDraftRecordAccess::class)
            ->bindImplementation(LayoutProcessorProvider::class, ConfiguredLayoutProcessorProvider::class)
            ->bindImplementation(
                SourceReferenceImpactAnalyzer::class,
                NoopSourceReferenceImpactAnalyzer::class,
            );
    }
}
