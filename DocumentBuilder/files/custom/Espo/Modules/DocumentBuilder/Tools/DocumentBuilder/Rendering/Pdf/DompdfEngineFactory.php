<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Tools\Pdf\Dompdf\DompdfInitializer;
use Espo\Tools\Pdf\Params;

final readonly class DompdfEngineFactory implements PdfEngineFactory
{
    public function __construct(private DompdfInitializer $initializer, private SettingsProvider $settingsProvider)
    {}

    public function create(ResolvedDocument $document, RenderWorkspace $workspace): PdfEngine
    {
        $settings = $this->settingsProvider->get();
        if ($settings->defaultPdfEngine() !== 'Dompdf') {
            throw new PdfRenderFailure('The configured PDF engine is unsupported.');
        }
        $dompdf = $this->initializer->initialize(
            new DocumentBuilderPdfTemplate($document, $settings),
            Params::create(),
        );
        $options = $dompdf->getOptions();
        $approvedChroot = $options->getChroot();
        if (!is_array($approvedChroot)) $approvedChroot = [];
        $approvedChroot[] = $workspace->path();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setTempDir($workspace->path());
        $options->setChroot(array_values(array_unique($approvedChroot)));
        $dompdf->setOptions($options);

        return new DompdfEngine($dompdf);
    }
}
