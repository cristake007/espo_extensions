<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Tools\Pdf\Dompdf\DompdfInitializer;
use Espo\Tools\Pdf\Params;

final readonly class DompdfEngineFactory implements PdfEngineFactory
{
    public function __construct(private DompdfInitializer $initializer, private Settings $settings)
    {}

    public function create(ResolvedDocument $document, RenderWorkspace $workspace): PdfEngine
    {
        if ($this->settings->defaultPdfEngine() !== 'Dompdf') {
            throw new PdfRenderFailure('The configured PDF engine is unsupported.');
        }
        $dompdf = $this->initializer->initialize(
            new DocumentBuilderPdfTemplate($document, $this->settings),
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
