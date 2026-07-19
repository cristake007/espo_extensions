<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
require dirname(__DIR__) . '/bootstrap.php';
$root=dirname(__DIR__,2); $module="$root/files/custom/Espo/Modules/DocumentBuilder"; $layoutRoot="$module/Tools/DocumentBuilder/Layout";
require "$module/Tools/DocumentBuilder/Config/Settings.php";
foreach (['SchemaVersion.php','StableId.php','Unit.php','Measurement.php','Capability.php','CapabilityStatus.php','CapabilityUnavailable.php','CapabilityNotPublishable.php','CapabilityRegistry.php','Node/NodeKind.php','Node/NodeDefinition.php','Node/UnknownNodeType.php','Node/NodeRegistry.php','ValidationError.php','ValidationResult.php','LayoutValidator.php'] as $file) require "$layoutRoot/$file";
$settings=new Settings(['enabledSourceEntityTypeList'=>[],'disabledSourceEntityTypeList'=>[],'maxRelationshipDepth'=>2,'maxElements'=>500,'maxNestingDepth'=>8,'maxSections'=>100,'allowedFontList'=>['DejaVu Sans'],'customPageSizeList'=>[]]);
$validator=new LayoutValidator($settings,NodeRegistry::phase19(),CapabilityRegistry::phase19());
$layout=(new FixtureLoader("$root/tests/fixtures"))->json('layout/phase-08-default.json');
$box=fn()=>array_fill_keys(['top','right','bottom','left'],['value'=>0,'unit'=>'mm']);
$elements=[
 ['id'=>'divider','type'=>'divider','orientation'=>'vertical','style'=>'double','color'=>'#123ABC','thickness'=>['value'=>1.5,'unit'=>'mm'],'length'=>['value'=>80,'unit'=>'mm']],
 ['id'=>'spacer','type'=>'spacer','height'=>['value'=>12.5,'unit'=>'mm']],
 ['id'=>'pageBreak','type'=>'page-break'],
];
$layout['capabilities']=['layout.flow']; $layout['sections']=[['id'=>'section','type'=>'flow-section','children'=>[['id'=>'container','type'=>'flow-container','children'=>$elements,'margin'=>$box(),'padding'=>$box(),'minHeight'=>['value'=>10,'unit'=>'mm'],'keepTogether'=>false]],'margin'=>$box(),'padding'=>$box(),'minHeight'=>['value'=>20,'unit'=>'mm'],'keepTogether'=>false,'startNewPage'=>false]];
$initialResult=$validator->validate($layout);Assert::isTrue($initialResult->isValid(),'Canonical Phase 21 elements were rejected: '.implode(',',array_map(fn($error)=>$error->code().':'.$error->path(),$initialResult->errors())));
$codes=fn($candidate)=>array_map(fn($error)=>$error->code(),$validator->validate($candidate)->errors());
$cases=[
 [0,'orientation','diagonal','divider.orientation'],[0,'style','groove','divider.style'],[0,'color','url(javascript:alert(1))','value.color'],
 [0,'thickness',['value'=>0,'unit'=>'mm'],'divider.thickness'],[0,'length',['value'=>2001,'unit'=>'mm'],'divider.length'],
 [1,'height',['value'=>501,'unit'=>'mm'],'spacer.height'],[2,'label','stored label','property.unknown'],
];
foreach($cases as [$index,$key,$value,$code]){$bad=$layout;$bad['sections'][0]['children'][0]['children'][$index][$key]=$value;Assert::isTrue(in_array($code,$codes($bad),true),"Invalid $key was accepted.");}
$bad=$layout;$bad['header']=[$elements[0]];
Assert::isTrue(in_array('flowElement.parent',$codes($bad),true),'A divider was accepted outside the flow hierarchy.');
echo "Phase 21 server element structure, enum, dimension, and unsafe-style validation tests passed.\n";
