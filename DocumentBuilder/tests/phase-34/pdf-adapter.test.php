<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfEngine;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfEngineFactory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfRenderFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfRenderResult;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\RenderWorkspace;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\RenderWorkspaceFactory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\SystemRenderWorkspaceFactory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\PdfRenderer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;

require dirname(__DIR__) . '/bootstrap.php';
$module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
require "$module/Config/Settings.php";
require "$module/Config/SettingsProvider.php";
foreach (['DocumentWarning.php','ResolvedNode.php','ResolvedDocument.php'] as $file) require "$module/Rendering/Tree/$file";
foreach (['PdfRenderFailure.php','PdfRenderResult.php','RenderWorkspace.php','RenderWorkspaceFactory.php',
    'SystemRenderWorkspace.php','SystemRenderWorkspaceFactory.php','PdfEngine.php','PdfEngineFactory.php'] as $file) {
    require "$module/Rendering/Pdf/$file";
}
require "$module/Rendering/PdfRenderer.php";

final class Phase34Workspace implements RenderWorkspace
{
    public bool $cleaned = false;
    public function path(): string { return sys_get_temp_dir(); }
    public function cleanup(): void { $this->cleaned = true; }
}
final class Phase34Workspaces implements RenderWorkspaceFactory
{
    /** @var list<Phase34Workspace> */ public array $created = [];
    public function create(): RenderWorkspace { return $this->created[] = new Phase34Workspace(); }
}
final class Phase34Engine implements PdfEngine
{
    public function __construct(private bool $fail = false, private int $pages = 1) {}
    public function render(string $html): PdfRenderResult
    {
        if ($this->fail) throw new RuntimeException('engine failure');
        return new PdfRenderResult('%PDF-1.7 safe', $this->pages);
    }
}
final class Phase34Engines implements PdfEngineFactory
{
    public int $created = 0;
    public function __construct(public bool $fail = false, public int $pages = 1) {}
    public function create(ResolvedDocument $document, RenderWorkspace $workspace): PdfEngine
    {
        $this->created++;
        return new Phase34Engine($this->fail, $this->pages);
    }
}
final readonly class Phase34PdfSettings implements SettingsProvider
{
    public function __construct(private Settings $settings) {}
    public function get(): Settings { return $this->settings; }
}

$settings = new Phase34PdfSettings(new Settings([
    'maxLayoutBytes'=>1048576,'renderMemoryMegabytes'=>64,'renderTimeoutSeconds'=>10,'maxRenderedPages'=>20,
]));
$document = new ResolvedDocument(['size'=>'A4','orientation'=>'portrait','margins'=>[]],[],[]);
$engines = new Phase34Engines();$workspaces = new Phase34Workspaces();
$renderer = new PdfRenderer($engines,$workspaces,$settings);
$first = $renderer->render($document,'<html>Știință</html>');
$second = $renderer->render($document,'<html>Alt document</html>');
Assert::same(2,$engines->created,'A fresh PDF engine was not created for every document.');
Assert::same('%PDF-1.7 safe',$first->bytes,'PDF bytes changed.');
Assert::same(1,$second->pageCount,'PDF page count changed.');
Assert::isTrue($workspaces->created[0]->cleaned && $workspaces->created[1]->cleaned,'Successful render workspaces were not cleaned.');

$failingEngines = new Phase34Engines(true);$failingWorkspaces = new Phase34Workspaces();
Assert::throws(fn () => (new PdfRenderer($failingEngines,$failingWorkspaces,$settings))->render($document,'<html></html>'),PdfRenderFailure::class,'Engine failures were not normalized.');
Assert::isTrue($failingWorkspaces->created[0]->cleaned,'Failed render workspace was not cleaned.');
$pageEngines = new Phase34Engines(false,21);
Assert::throws(fn () => (new PdfRenderer($pageEngines,new Phase34Workspaces(),$settings))->render($document,'<html></html>'),PdfRenderFailure::class,'Page limit was not enforced.');

$systemWorkspace = (new SystemRenderWorkspaceFactory())->create();
$systemPath = $systemWorkspace->path();
file_put_contents($systemPath . '/probe.tmp','temporary');
$systemWorkspace->cleanup();
Assert::isFalse(file_exists($systemPath),'System render workspace was not removed.');

echo "Phase 34 fresh-engine, resource-limit, and cleanup tests passed.\n";
