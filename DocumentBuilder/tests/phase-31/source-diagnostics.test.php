<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\CompiledSourceReferenceImpactAnalyzer;

require dirname(__DIR__) . '/phase-26/compiler.test.php';

$module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
require "$module/DataSource/Variable/VariableValueType.php";
foreach (['ConditionMode.php', 'ConditionTarget.php', 'ConditionOperator.php', 'ConditionRule.php',
    'VisibilityCondition.php'] as $file) {
    require "$module/Layout/Condition/$file";
}
foreach (['UnresolvedSourceReference.php', 'SourceReferenceImpactAnalyzer.php',
    'CompiledSourceReferenceImpactAnalyzer.php'] as $file) {
    require "$module/Draft/$file";
}

$nextLayout = [
    'dataSource' => ['type'=>'entity', 'entityType'=>'Account', 'relationshipDepth'=>2],
    'sections' => [[
        'id' => 'section1',
        'type' => 'flow-section',
        'condition' => ['target'=>'parent', 'mode'=>'all', 'rules'=>[[
            'identity'=>$direct, 'valueType'=>'text', 'operator'=>'exists', 'operand'=>null,
        ]]],
        'content' => [[
            'type'=>'variable', 'tokenId'=>'token1', 'identity'=>$direct,
        ], [
            'type'=>'variable', 'tokenId'=>'token2',
            'identity'=>['source'=>'system', 'type'=>'system', 'path'=>['currentDate']],
        ]],
        'children' => [[
            'id'=>'variable1', 'type'=>'variable', 'identity'=>$direct,
        ]],
    ]],
];
$impact = (new CompiledSourceReferenceImpactAnalyzer($compiler))->analyze([
    'dataSource'=>$entitySource,
], $nextLayout);

Assert::same(3, count($impact), 'Source analysis did not report every incompatible element, inline, and condition reference.');
Assert::same('section1', $impact[0]->id, 'Condition impact lost its owning node.');
Assert::same('token1', $impact[1]->id, 'Inline impact lost its token identity.');
Assert::same('variable1', $impact[2]->id, 'Standalone variable impact lost its element identity.');
Assert::contains('/condition/rules/0', $impact[0]->path, 'Condition impact path is incomplete.');

echo "Phase 31 complete source-switch impact tests passed.\n";
