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
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\AclTemplateLifecycleAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\OrmTemplateLifecycleStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\AclPublicationRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\CurrentUserPublicationActor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\DataSourcePublicationValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\MediaPublicationValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\NoopMediaPublicationValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\NoopVariablePublicationValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\OrmPublicationStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\Phase13DataSourcePublicationValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationActor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\VariablePublicationValidator;

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
            )
            ->bindImplementation(PublicationStore::class, OrmPublicationStore::class)
            ->bindImplementation(PublicationRecordAccess::class, AclPublicationRecordAccess::class)
            ->bindImplementation(PublicationActor::class, CurrentUserPublicationActor::class)
            ->bindImplementation(
                DataSourcePublicationValidator::class,
                Phase13DataSourcePublicationValidator::class,
            )
            ->bindImplementation(MediaPublicationValidator::class, NoopMediaPublicationValidator::class)
            ->bindImplementation(
                VariablePublicationValidator::class,
                NoopVariablePublicationValidator::class,
            )
            ->bindImplementation(
                TemplateLifecycleStore::class,
                OrmTemplateLifecycleStore::class,
            )
            ->bindImplementation(
                TemplateLifecycleAccess::class,
                AclTemplateLifecycleAccess::class,
            );
    }
}
