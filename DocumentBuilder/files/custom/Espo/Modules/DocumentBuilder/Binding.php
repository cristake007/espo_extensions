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
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\AclEntityCatalogueAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\ConfiguredEntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityLabelProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourceEligibility;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\ConfiguredRelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EspoEntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\LanguageEntityLabelProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\AclEntityResolutionAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityPathResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\OrmEntityRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\OrmRelatedRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\CompiledVariableReferenceValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableReferenceValidator;
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
            )
            ->bindImplementation(EntityCatalogueMetadata::class, EspoEntityCatalogueMetadata::class)
            ->bindImplementation(EntityCatalogueAccess::class, AclEntityCatalogueAccess::class)
            ->bindImplementation(EntitySourcePolicy::class, ConfiguredEntitySourcePolicy::class)
            ->bindImplementation(EntitySourceEligibility::class, EntityCatalogueService::class)
            ->bindImplementation(RelationshipDepthLimit::class, ConfiguredRelationshipDepthLimit::class)
            ->bindImplementation(EntityResolver::class, EntityPathResolver::class)
            ->bindImplementation(EntityRecordReader::class, OrmEntityRecordReader::class)
            ->bindImplementation(RelatedRecordReader::class, OrmRelatedRecordReader::class)
            ->bindImplementation(EntityResolutionAccess::class, AclEntityResolutionAccess::class)
            ->bindImplementation(
                VariableReferenceValidator::class,
                CompiledVariableReferenceValidator::class,
            )
            ->bindImplementation(EntityLabelProvider::class, LanguageEntityLabelProvider::class);
    }
}
