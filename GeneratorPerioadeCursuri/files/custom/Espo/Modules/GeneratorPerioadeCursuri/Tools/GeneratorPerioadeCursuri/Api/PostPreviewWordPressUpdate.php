<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUpdaterHttpException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUpdaterService;

class PostPreviewWordPressUpdate implements Action
{
    public function __construct(
        private WordPressUpdaterService $service,
        private Language $language
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || trim($id) === '') {
            throw new BadRequest($this->language->translateLabel(
                'wpUpdaterIdRequired',
                'messages',
                'GeneratorPerioadeCursuriWordPressUpdater'
            ));
        }

        try {
            return ResponseComposer::json($this->service->preview($id, $request->getParsedBody()));
        } catch (WordPressUpdaterHttpException $exception) {
            return ResponseComposer::json(['error' => $exception->getMessage()])
                ->setStatus($exception->getStatus());
        }
    }
}
