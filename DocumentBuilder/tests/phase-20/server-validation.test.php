<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\RichTextSanitizer;
require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2); $module = "$root/files/custom/Espo/Modules/DocumentBuilder"; $layoutRoot = "$module/Tools/DocumentBuilder/Layout";
require "$module/Tools/DocumentBuilder/Config/Settings.php";
foreach (['SchemaVersion.php','StableId.php','Unit.php','Measurement.php','Capability.php','CapabilityStatus.php','CapabilityUnavailable.php','CapabilityNotPublishable.php','CapabilityRegistry.php','Node/NodeKind.php','Node/NodeDefinition.php','Node/UnknownNodeType.php','Node/NodeRegistry.php','ValidationError.php','ValidationResult.php','LayoutValidator.php','RichTextSanitizer.php'] as $file) require "$layoutRoot/$file";
$settings = new Settings([
    'enabledSourceEntityTypeList'=>[],'disabledSourceEntityTypeList'=>[],
    'maxRelationshipDepth'=>2,'maxElements'=>500,'maxNestingDepth'=>8,'maxSections'=>100,
    'allowedFontList'=>['DejaVu Sans'],'customPageSizeList'=>[],
]);
$validator = new LayoutValidator($settings, NodeRegistry::phase19(), CapabilityRegistry::phase19());
$layout = (new FixtureLoader("$root/tests/fixtures"))->json('layout/phase-08-default.json');
$box = fn () => array_fill_keys(['top','right','bottom','left'], ['value'=>0,'unit'=>'mm']);
$presentation = ['format'=>['type'=>'auto','decimals'=>2,'dateStyle'=>'medium','timeStyle'=>'short','currency'=>null,'trueLabel'=>null,'falseLabel'=>null,'separator'=>', ','trim'=>true,'case'=>'none','prefix'=>'','suffix'=>'','fallback'=>null],'missing'=>'empty'];
$layout['capabilities'] = ['layout.flow'];
$layout['sections'] = [[
    'id'=>'section','type'=>'flow-section','children'=>[[
        'id'=>'container','type'=>'flow-container','children'=>[
            ['id'=>'heading','type'=>'heading','content'=>[['type'=>'text','text'=>'<script>alert(1)</script>','marks'=>['bold'],'color'=>'#123ABC'],['type'=>'variable','tokenId'=>'token_name','label'=>'Name','identity'=>['source'=>'system','type'=>'system','path'=>['currentDate']],'presentation'=>$presentation]],'level'=>2,'keepWithNext'=>true],
            ['id'=>'static','type'=>'static-text','text'=>'<img onerror=alert(1)>'],
            ['id'=>'paragraph','type'=>'paragraph','content'=>[['type'=>'text','text'=>'Safe','marks'=>['italic']],['type'=>'break']],'alignment'=>'justify'],
        ],'margin'=>$box(),'padding'=>$box(),'minHeight'=>['value'=>10,'unit'=>'mm'],'keepTogether'=>false,
    ]],'margin'=>$box(),'padding'=>$box(),'minHeight'=>['value'=>20,'unit'=>'mm'],'keepTogether'=>false,'startNewPage'=>false,
]];
$initialResult = $validator->validate($layout);
Assert::isTrue($initialResult->isValid(), 'Literal markup represented as text must remain valid and escaped at rendering: ' . implode(',', array_map(fn ($error) => $error->code(), $initialResult->errors())));
$codes = fn ($value) => array_map(fn ($error) => $error->code(), $validator->validate($value)->errors());
foreach ([
    ['key'=>'html','value'=>'<b>raw</b>','code'=>'property.unknown'],
    ['key'=>'style','value'=>'background:url(javascript:alert(1))','code'=>'style.type'],
    ['key'=>'onerror','value'=>'alert(1)','code'=>'property.unknown'],
] as $case) {
    $bad=$layout; $bad['sections'][0]['children'][0]['children'][0][$case['key']]=$case['value'];
    Assert::isTrue(in_array($case['code'], $codes($bad), true), "Unsafe {$case['key']} field was accepted.");
}
foreach (['iframe','link','raw-html'] as $type) {
    $bad=$layout; $bad['sections'][0]['children'][0]['children'][2]['content']=[['type'=>$type,'url'=>'javascript:alert(1)']];
    Assert::isTrue(in_array('content.type', $codes($bad), true), "Unsafe inline type $type was accepted.");
}
$bad=$layout; $bad['sections'][0]['children'][0]['children'][2]['content']=[['type'=>'link','url'=>'data:text/html,<script>alert(1)</script>']];
Assert::isTrue(in_array('content.type', $codes($bad), true), 'A data-URL inline link was accepted.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][1]['label']='';
Assert::isTrue(in_array('content.tokenLabel', $codes($bad), true), 'An empty inline-variable label was accepted.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][1]['identity']=['source'=>'entity','type'=>'collection','entityType'=>'Contact','path'=>['courses']];
Assert::isTrue(in_array('content.variableIdentity', $codes($bad), true), 'A collection identity was accepted in scalar inline content.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][1]['identity']=['source'=>'entity','type'=>'direct','entityType'=>'Contact','path'=>['account','name']];
Assert::isTrue(in_array('content.variableIdentity', $codes($bad), true), 'A non-canonical direct identity was accepted.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][1]['presentation']['format']['expression']='value.toString()';
Assert::isTrue(in_array('content.variablePresentation', $codes($bad), true), 'An arbitrary format expression was accepted.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][1]['presentation']['missing']='fallback';
Assert::isTrue(in_array('content.variablePresentation', $codes($bad), true), 'A fallback policy without fallback text was accepted.');
$bad=$layout; $bad['sections'][0]['children'][0]['children'][0]['content'][0]['marks']=['bold','bold'];
Assert::isTrue(in_array('content.marks', $codes($bad), true), 'Duplicate formatting marks were accepted.');
$normalizer = new RichTextSanitizer(); $dirty=$layout;
$dirty['sections'][0]['children'][0]['children'][0]['content'][0]['text']="a\r\nb";
$dirty['sections'][0]['children'][0]['children'][0]['content'][0]['marks']=['underline','bold','underline'];
$dirty['sections'][0]['children'][0]['children'][0]['content'][1]['presentation']['format']['fallback']="a\r\nb";
$normalized=$normalizer->normalizeLayout($dirty);
Assert::same("a\nb", $normalized['sections'][0]['children'][0]['children'][0]['content'][0]['text'], 'Server newline normalization failed.');
Assert::same(['bold','underline'], $normalized['sections'][0]['children'][0]['children'][0]['content'][0]['marks'], 'Server mark canonicalization failed.');
Assert::same("a\nb", $normalized['sections'][0]['children'][0]['children'][0]['content'][1]['presentation']['format']['fallback'], 'Variable presentation text normalization failed.');
Assert::isTrue($validator->validate($normalized)->isValid(), 'Server-authoritative normalized content was rejected.');
$bad=$layout;$bad['footer']=[$layout['sections'][0]['children'][0]['children'][0]];
Assert::isTrue(in_array('content.parent',$codes($bad),true),'Content was accepted outside the flow hierarchy.');
echo "Phase 20 server content validation, rejection, and normalization tests passed.\n";
