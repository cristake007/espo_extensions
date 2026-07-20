<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValuePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValueResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormat;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatContext;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatter;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariablePresentation;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\ConditionEvaluator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\RequiredVariableFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\StyleResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\DocumentTreeBuilder;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\DocumentValue;

require dirname(__DIR__) . '/bootstrap.php';
$module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
foreach (['VariableSource.php', 'VariableType.php', 'VariableUsage.php', 'VariablePath.php',
    'VariableIdentity.php', 'VariableValueType.php', 'VariableValueState.php', 'VariableValue.php',
    'FormatType.php', 'TextCase.php', 'MissingValuePolicy.php', 'MissingValueDisposition.php',
    'VariableFormat.php', 'VariablePresentation.php', 'VariableFormatContext.php',
    'FormattedVariableValue.php', 'MissingValueResolver.php', 'VariableFormatter.php'] as $file) {
    require "$module/DataSource/Variable/$file";
}
foreach (['ConditionMode.php', 'ConditionTarget.php', 'ConditionOperator.php', 'ConditionRule.php',
    'VisibilityCondition.php', 'ConditionEvaluation.php', 'ConditionEvaluator.php',
    'RequiredVariableFailure.php'] as $file) {
    require "$module/Layout/Condition/$file";
}
require "$module/Layout/ResolvedStyleProvider.php";
require "$module/Layout/StyleResolver.php";
foreach (['DocumentValue.php', 'DocumentWarning.php', 'ResolvedInline.php', 'ResolvedNode.php',
    'ResolvedDocument.php'] as $file) {
    require "$module/Rendering/Tree/$file";
}
require "$module/Rendering/DocumentTreeBuilder.php";

$builder = new DocumentTreeBuilder(
    new VariableFormatter(new MissingValueResolver()),
    new ConditionEvaluator(),
    new StyleResolver(['DejaVu Sans']),
);
$context = new VariableFormatContext('ro_RO', 'Europe/Bucharest');
$identityData = ['source'=>'entity', 'type'=>'direct', 'entityType'=>'Contact', 'path'=>['name']];
$identity = VariableIdentity::fromArray($identityData);
$presentation = (new VariablePresentation())->toArray();
$box = ['top'=>['value'=>0,'unit'=>'mm'], 'right'=>['value'=>0,'unit'=>'mm'],
    'bottom'=>['value'=>0,'unit'=>'mm'], 'left'=>['value'=>0,'unit'=>'mm']];
$layout = [
    'document'=>['page'=>['size'=>'A4','orientation'=>'portrait','margins'=>$box],
        'defaults'=>['fontFamily'=>'DejaVu Sans','fontSize'=>['value'=>10,'unit'=>'pt'],'color'=>'#222222','lineHeight'=>1.2]],
    'sections'=>[[
        'id'=>'section1','type'=>'flow-section','margin'=>$box,'padding'=>$box,
        'minHeight'=>['value'=>20,'unit'=>'mm'],'keepTogether'=>false,'startNewPage'=>false,
        'children'=>[
            ['id'=>'static1','type'=>'static-text','content'=>[
                ['type'=>'text','text'=>'Certificat','marks'=>['bold']],
            ]],
            ['id'=>'paragraph1','type'=>'paragraph','alignment'=>'start','content'=>[
                ['type'=>'text','text'=>'Nume: ','marks'=>[]],
                ['type'=>'variable','tokenId'=>'token1','label'=>'Name','identity'=>$identityData,'presentation'=>$presentation],
                ['type'=>'list','style'=>'bulleted','items'=>[
                    [['type'=>'text','text'=>'Participant','marks'=>['bold']]],
                    [['type'=>'variable','tokenId'=>'token2','label'=>'Name','identity'=>$identityData,'presentation'=>$presentation]],
                ]],
            ]],
            ['id'=>'variable1','type'=>'variable','label'=>'Name','identity'=>$identityData,'presentation'=>$presentation],
        ],
    ]],
];
$values = [new DocumentValue(
    $identity,
    new VariableValue(VariableValueType::Text, VariableValueState::Present, '<script>raw name</script>'),
    ['source'=>'entity','entityType'=>'Contact','recordId'=>'contact1','field'=>'name'],
)];
$tree = $builder->build($layout, $values, $context);
$snapshot = $tree->canonicalJson();
Assert::same($snapshot, $builder->build($layout, $values, $context)->canonicalJson(), 'Tree snapshots are not deterministic.');
Assert::contains('<script>raw name</script>', $snapshot, 'The tree changed safe text before the renderer boundary.');
Assert::same(['static1', 'paragraph1', 'variable1'], array_map(
    static fn ($node): string => $node->id,
    $tree->sections[0]->children,
), 'Stable node order or IDs changed.');
Assert::same([], $tree->sections[0]->collectionSlots, 'Future collection slots are not initialized safely.');
Assert::same('list', $tree->sections[0]->children[1]->inline[2]->type, 'Structured list did not enter the resolved tree.');
Assert::same(['bold'], $tree->sections[0]->children[0]->inline[0]->marks, 'Structured static text did not enter the resolved tree.');
Assert::same('variable', $tree->sections[0]->children[1]->inline[2]->items[1][0]->type, 'A list variable was not resolved.');
Assert::same('<script>raw name</script>', $tree->sections[0]->children[2]->inline[0]->text, 'Standalone variable was not resolved.');

$conditionLayout = $layout;
$conditionLayout['sections'][0]['children'][0]['condition'] = ['target'=>'element','mode'=>'all','rules'=>[[
    'identity'=>$identityData,'valueType'=>'text','operator'=>'equals','operand'=>'Visible',
]]];
$hidden = $builder->build($conditionLayout, $values, $context);
Assert::same(['paragraph1', 'variable1'], array_map(static fn ($node): string => $node->id, $hidden->sections[0]->children), 'Hidden condition nodes entered the resolved tree.');

$warningLayout = $layout;
$warningLayout['sections'][0]['children'][1]['content'][1]['presentation'] =
    (new VariablePresentation(new VariableFormat(fallback: '[unavailable]'), MissingValuePolicy::Warning))->toArray();
$forbidden = $builder->build($warningLayout, [new DocumentValue(
    $identity,
    new VariableValue(VariableValueType::Text, VariableValueState::Forbidden),
    ['source'=>'entity','recordId'=>'secret-record'],
)], $context);
$forbiddenJson = $forbidden->canonicalJson();
Assert::contains('variable.forbidden', $forbiddenJson, 'Forbidden warning path is missing.');
Assert::isFalse(str_contains($forbiddenJson, 'secret-record'), 'Forbidden provenance entered the resolved tree.');
Assert::isFalse(str_contains($forbiddenJson, 'raw name'), 'A forbidden raw value entered the resolved tree.');

$requiredLayout = $layout;
$requiredLayout['sections'][0]['children'][1]['content'][1]['presentation'] =
    (new VariablePresentation(missing: MissingValuePolicy::Required))->toArray();
Assert::throws(fn () => $builder->build($requiredLayout, [], $context), RequiredVariableFailure::class, 'Missing required data did not stop tree construction.');

$chromeLayout = $layout;
$chromeLayout['document']['chrome'] = [
    'header' => ['height'=>['value'=>10,'unit'=>'mm'], 'showOnFirstPage'=>true, 'disableOnFullPage'=>true],
    'footer' => ['height'=>['value'=>8,'unit'=>'mm'], 'showOnFirstPage'=>true, 'disableOnFullPage'=>true],
];
$chromeLayout['header'] = [[
    'id'=>'header1', 'type'=>'paragraph', 'alignment'=>'end', 'content'=>[[
        'type'=>'variable', 'tokenId'=>'pageNumber1', 'label'=>'Page Number',
        'identity'=>['source'=>'system', 'type'=>'system', 'path'=>['pageNumber']],
        'presentation'=>$presentation,
    ]],
]];
$chromeLayout['footer'] = [['id'=>'footer1', 'type'=>'static-text', 'text'=>'Confidential']];
$chromeTree = $builder->build($chromeLayout, $values, $context);
Assert::same('page-number', $chromeTree->header[0]->inline[0]->type, 'Current-page placeholder was resolved as ordinary data.');
Assert::same('footer1', $chromeTree->footer[0]->id, 'Footer did not enter the resolved tree.');
Assert::same($chromeLayout['document']['chrome'], $chromeTree->chrome, 'Page chrome render settings changed in the tree.');
$chromeLayout['header'][0]['content'][0]['identity']['path'] = ['pageCount'];
$pageCountTree = $builder->build($chromeLayout, $values, $context);
Assert::same('page-count', $pageCountTree->header[0]->inline[0]->type, 'Total-page placeholder did not enter the render tree.');

echo "Phase 32 immutable resolved-tree tests passed.\n";
