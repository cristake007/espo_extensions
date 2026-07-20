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
foreach (['DocumentWarning.php', 'ResolvedInline.php', 'ResolvedNode.php', 'ResolvedDocument.php'] as $file) {
    require "$module/Tree/$file";
}
foreach (['ElementDefinition.php', 'ElementRendererRegistry.php', 'TypedStyleMapper.php'] as $file) {
    require "$module/Html/$file";
}
require "$module/HtmlRenderer.php";

$margins = array_fill_keys(['top', 'right', 'bottom', 'left'], ['value' => 15, 'unit' => 'mm']);
$defaults = [
    'fontFamily' => 'DejaVu Sans',
    'fontSize' => ['value' => 10, 'unit' => 'pt'],
    'color' => '#222222',
    'lineHeight' => 1.2,
    'locale' => 'en_US',
];
$header = new ResolvedNode('header-1', 'paragraph', $defaults, ['alignment' => 'end'], [
    new ResolvedInline('text', 'Invoice '),
    new ResolvedInline('page-number', ''),
]);
$footer = new ResolvedNode('footer-1', 'static-text', $defaults, [], [
    new ResolvedInline('text', 'Confidential'),
]);
$tree = new ResolvedDocument(
    ['size' => 'A4', 'orientation' => 'portrait', 'margins' => $margins],
    $defaults,
    [],
    [],
    [$header],
    [$footer],
    [
        'header' => [
            'height' => ['value' => 10, 'unit' => 'mm'],
            'showOnFirstPage' => false,
            'disableOnFullPage' => true,
        ],
        'footer' => [
            'height' => ['value' => 8, 'unit' => 'mm'],
            'showOnFirstPage' => true,
            'disableOnFullPage' => false,
        ],
    ],
);

$renderer = new HtmlRenderer(new ElementRendererRegistry(), new TypedStyleMapper());
$html = $renderer->render($tree);

Assert::contains('<header class="db-page-header" style="position:fixed;top:-15mm;', $html, 'Header is not positioned from the page margin.');
Assert::contains('<footer class="db-page-footer" style="position:fixed;bottom:-15mm;', $html, 'Footer is not positioned from the page margin.');
Assert::contains('data-show-first-page="0"', $html, 'First-page visibility was not preserved.');
Assert::contains('data-disable-on-full-page="0"', $html, 'Full-page suppression preference was not preserved.');
Assert::contains('<span class="db-page-number" aria-label="Page number"></span>', $html, 'Current-page renderer placeholder is missing.');
Assert::contains('.db-page-number::after{content:counter(page);}', $html, 'Current-page PDF counter rule is missing.');
$bodyResetOffset = strpos($html, 'html,body{margin:0;padding:0;}');
$pageRuleOffset = strpos($html, '@page{');
Assert::isTrue(
    is_int($bodyResetOffset) && is_int($pageRuleOffset) && $bodyResetOffset < $pageRuleOffset,
    'The Dompdf body reset overrides the configured page margins.',
);
Assert::same($html, $renderer->render($tree), 'Page chrome HTML is not deterministic.');

echo "Phase 35 deterministic header/footer HTML tests passed.\n";
