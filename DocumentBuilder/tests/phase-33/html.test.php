<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html\ElementRendererRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html\TypedStyleMapper;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\HtmlRenderer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedInline;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedNode;

require dirname(__DIR__) . '/bootstrap.php';
$module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering';
foreach (['DocumentWarning.php','ResolvedInline.php','ResolvedNode.php','ResolvedDocument.php'] as $file) require "$module/Tree/$file";
foreach (['ElementDefinition.php','ElementRendererRegistry.php','TypedStyleMapper.php'] as $file) require "$module/Html/$file";
require "$module/HtmlRenderer.php";

$defaults = ['fontFamily'=>'DejaVu Sans','fontSize'=>['value'=>10,'unit'=>'pt'],
    'color'=>'#222222','lineHeight'=>1.2,'locale'=>'ro_RO'];
$box = ['top'=>['value'=>10,'unit'=>'mm'],'right'=>['value'=>11,'unit'=>'mm'],
    'bottom'=>['value'=>12,'unit'=>'mm'],'left'=>['value'=>13,'unit'=>'mm']];
$heading = new ResolvedNode('heading1','heading',[
    ...$defaults,'fontFamily'=>'Bad\';background:url(https://attacker.invalid)',
    'backgroundColor'=>'#FFFFFF','textTransform'=>'uppercase','raw'=>'position:fixed',
],['level'=>2,'keepWithNext'=>true],[
    new ResolvedInline('text','<script>alert("x")</script>',['bold','italic'],'#AABBCC'),
]);
$paragraph = new ResolvedNode('paragraph1','paragraph',$defaults,['alignment'=>'start'],[
    new ResolvedInline('text','Bună & „lume”',[]),new ResolvedInline('break',"\n"),
    new ResolvedInline('variable','Ștefan <Admin>'),
    new ResolvedInline('list', '', listStyle: 'numbered', items: [
        [new ResolvedInline('text', 'Primul <element>')],
        [new ResolvedInline('variable', 'Al doilea & final')],
    ]),
]);
$variable = new ResolvedNode('variable1','variable',$defaults,[],[
    new ResolvedInline('variable','Valoare <sigură>'),
]);
$divider = new ResolvedNode('divider1','divider',$defaults,[
    'orientation'=>'horizontal','lineStyle'=>'dashed','color'=>'#123456',
    'thickness'=>['value'=>0.5,'unit'=>'mm'],'length'=>['value'=>100,'unit'=>'mm'],
]);
$spacer = new ResolvedNode('spacer1','spacer',$defaults,['height'=>['value'=>8,'unit'=>'mm']]);
$break = new ResolvedNode('break1','page-break',$defaults,[]);
$section = new ResolvedNode('section1','flow-section',$defaults,[
    'margin'=>$box,'padding'=>$box,'minHeight'=>['value'=>20,'unit'=>'mm'],
    'keepTogether'=>true,'startNewPage'=>false,
],[],[$heading,$paragraph,$variable,$divider,$spacer,$break]);
$tree = new ResolvedDocument(['size'=>'A4','orientation'=>'portrait','margins'=>$box],$defaults,[$section]);
$before = $tree->canonicalJson();
$renderer = new HtmlRenderer(new ElementRendererRegistry(), new TypedStyleMapper());
$html = $renderer->render($tree);

Assert::same($before, $tree->canonicalJson(), 'HTML rendering mutated the resolved tree.');
Assert::same($html, $renderer->render($tree), 'HTML output is not deterministic.');
Assert::contains('<!doctype html><html lang="ro-RO"><head><meta charset="UTF-8">',$html,'Document envelope changed.');
Assert::contains('@page{size:A4 portrait;margin:10mm 11mm 12mm 13mm;}',$html,'Page geometry CSS changed.');
Assert::contains('<h2 id="heading1" class="db-heading"',$html,'Heading renderer is missing.');
Assert::contains('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',$html,'Text injection was not escaped.');
Assert::contains('Bună &amp; „lume”<br>Ștefan &lt;Admin&gt;',$html,'Romanian inline rendering changed.');
Assert::contains('<ol><li>Primul &lt;element&gt;</li><li>Al doilea &amp; final</li></ol>',$html,'Structured list rendering changed.');
Assert::contains('<div id="variable1" class="db-variable"',$html,'Standalone variable renderer is missing.');
Assert::contains('page-break-after:avoid;',$html,'Keep-with-next CSS is missing.');
Assert::contains('page-break-after:always;',$html,'Explicit page break CSS is missing.');
Assert::contains('border-top:0.5mm dashed #123456;',$html,'Typed divider CSS changed.');
Assert::isFalse(str_contains($html,'attacker.invalid'),'An arbitrary CSS URL entered HTML.');
Assert::isFalse(str_contains($html,'position:fixed'),'A non-allowlisted style entered HTML.');
Assert::isFalse(str_contains($html,'<script>'),'Raw script markup entered HTML.');

echo "Phase 33 deterministic conservative HTML tests passed.\n";
