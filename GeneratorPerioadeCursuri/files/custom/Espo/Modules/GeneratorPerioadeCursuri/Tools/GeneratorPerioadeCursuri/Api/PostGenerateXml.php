<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\XmlConversionService;

class PostGenerateXml implements Action
{
    public function __construct(
        private XmlConversionService $service,
        private Language $language
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || trim($id) === '') {
            throw new BadRequest($this->language->translateLabel(
                'xmlConverterIdRequired',
                'messages',
                'GeneratorPerioadeCursuriXmlConverter'
            ));
        }

        try {
            return ResponseComposer::json($this->service->generate($id));
        } catch (BadRequest $e) {
            if ($e->getCode() !== 413) {
                throw $e;
            }

            return ResponseComposer::json(['error' => $e->getMessage()])->setStatus(413);
        }
    }
}
