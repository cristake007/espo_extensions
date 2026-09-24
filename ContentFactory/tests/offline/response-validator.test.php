<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Metadata
    {
        /** @param array<string, mixed> $definition */
        public function __construct(private array $definition)
        {}

        /** @param list<string> $path */
        public function get(array $path): mixed
        {
            return $this->definition;
        }
    }
}

namespace {
    use Espo\Core\Utils\Metadata;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationRequest;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\ContractRegistry;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\ResponseValidator;

    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/ContentFactory';

    require $module . '/Tools/ContentFactory/Generation/GenerationException.php';
    require $module . '/Tools/ContentFactory/Generation/GenerationRequest.php';
    require $module . '/Tools/ContentFactory/Validation/ContractRegistry.php';
    require $module . '/Tools/ContentFactory/Validation/ResponseValidator.php';

    $metadata = json_decode(
        file_get_contents($module . '/Resources/metadata/app/contentFactory.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $registry = new ContractRegistry(new Metadata($metadata['generator']));
    $validator = new ResponseValidator($registry);

    $safeArea = [
        'topPercent' => 8,
        'rightPercent' => 8,
        'bottomPercent' => 8,
        'leftPercent' => 8,
    ];
    $direction = [
        'stylePrompt' => 'Realistic professional B2B visual style in a modern industrial workplace.',
        'continuityPrompt' => 'Maintain the same people, clothing, lighting, and workplace across every scene.',
        'negativePrompt' => 'No generated text, logos, watermarks, artifacts, or distorted hands.',
    ];
    $post = [
        'headline' => 'Calitatea începe cu procese clare',
        'text' => 'Procesele clare reduc erorile și ajută echipele să ia decizii consecvente.',
        'cta' => 'Află mai multe.',
        'hashtags' => ['#Calitate', '#Training'],
        'image' => [
            'aspectRatio' => '4:5',
            'width' => 1080,
            'height' => 1350,
            'safeArea' => $safeArea,
            'visualPrompt' => 'Professional B2B photograph of a Romanian quality team reviewing a clear process board.',
            'headline' => 'Procese clare. Riscuri mai mici.',
            'supportingText' => '',
        ],
    ];
    $reel = [
        'hook' => 'Trei greșeli care afectează calitatea',
        'durationSeconds' => 15,
        'aspectRatio' => '9:16',
        'width' => 1080,
        'height' => 1920,
        'caption' => 'Descoperă rapid unde apar riscurile într-un proces neclar.',
        'cta' => 'Află mai multe.',
        'hashtags' => ['#Calitate', '#Training'],
        'visualDirection' => $direction,
        'scenes' => [
            [
                'order' => 1,
                'durationSeconds' => 5,
                'visualPrompt' => 'A quality specialist notices an unclear instruction on a production checklist.',
                'voiceover' => 'Un proces neclar produce probleme înainte să le observi.',
                'onScreenText' => 'Procese neclare = risc',
            ],
            [
                'order' => 2,
                'durationSeconds' => 5,
                'visualPrompt' => 'The specialist and operator organize the checklist into three clear steps.',
                'voiceover' => 'Pașii bine definiți reduc interpretările și erorile.',
                'onScreenText' => 'Claritate în fiecare pas',
            ],
            [
                'order' => 3,
                'durationSeconds' => 5,
                'visualPrompt' => 'The same team confidently completes the verified process at the workstation.',
                'voiceover' => 'Verificarea consecventă transformă procesul într-un rezultat sigur.',
                'onScreenText' => 'Verifică. Aplică. Îmbunătățește.',
            ],
        ],
    ];
    $request = new GenerationRequest(
        'Brief pentru calitate',
        'instagram',
        'post-reel',
        'automatic',
        '',
        'ro',
        'general',
    );
    $response = [
        'schemaVersion' => 1,
        'requestId' => 'request-1',
        'success' => true,
        'platform' => 'instagram',
        'format' => 'post-reel',
        'locale' => 'ro',
        'content' => ['post' => $post, 'reel' => $reel],
    ];

    $validated = $validator->validate($response, $request, 'request-1');

    if ($validated !== $response) {
        throw new RuntimeException('Valid post-reel response was changed or rejected.');
    }

    $withoutHashtags = $response;
    unset($withoutHashtags['content']['post']['hashtags']);
    $validator->validate($withoutHashtags, $request, 'request-1');

    $withEmptyHashtags = $response;
    $withEmptyHashtags['content']['post']['hashtags'] = [];
    $validator->validate($withEmptyHashtags, $request, 'request-1');

    $withImageAssets = $response;
    $withImageAssets['content']['post']['image']['imageUrl'] =
        'https://cdn.example.test/generated/post.png';
    $withImageAssets['content']['post']['image']['imageDataUrl'] =
        'data:image/png;base64,iVBORw0KGgo=';
    $validator->validate($withImageAssets, $request, 'request-1');

    $linkedInRequest = new GenerationRequest(
        'Brief pentru LinkedIn',
        'linkedin',
        'post',
        'automatic',
        '',
        'ro',
        'general',
    );
    $linkedInResponse = [
        'schemaVersion' => 1,
        'requestId' => 'linkedin-request-1',
        'success' => true,
        'platform' => 'linkedin',
        'format' => 'post',
        'locale' => 'ro',
        'content' => ['post' => $post],
        'assets' => [
            'images' => [[
                'url' => 'https://cdn.example.test/generated/linkedin-post.png',
            ]],
            'videos' => [],
        ],
    ];
    $linkedInValidated = $validator->validate(
        $linkedInResponse,
        $linkedInRequest,
        'linkedin-request-1',
    );

    if (
        isset($linkedInValidated['assets']) ||
        ($linkedInValidated['content']['post']['image']['imageUrl'] ?? null) !==
            'https://cdn.example.test/generated/linkedin-post.png'
    ) {
        throw new RuntimeException('LinkedIn post image asset was not normalized.');
    }

    $linkedInDataResponse = $linkedInResponse;
    $linkedInDataResponse['assets']['images'][0] = [
        'slot' => 'post-main',
        'fileName' => 'linkedin-request-1-post-main.jpg',
        'mimeType' => 'image/jpeg',
        'width' => 1080,
        'height' => 1350,
        'aspectRatio' => '4:5',
        'dataBase64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2Q==',
    ];
    $linkedInDataValidated = $validator->validate(
        $linkedInDataResponse,
        $linkedInRequest,
        'linkedin-request-1',
    );

    if (
        ($linkedInDataValidated['content']['post']['image']['imageDataUrl'] ?? null) !==
            'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2Q=='
    ) {
        throw new RuntimeException('LinkedIn post dataBase64 asset was not normalized.');
    }

    $linkedInLegacyDataResponse = $linkedInResponse;
    $linkedInLegacyDataResponse['assets']['images'][0] = [
        'mimeType' => 'image/png',
        'data' => 'iVBORw0KGgo=',
    ];
    $linkedInLegacyDataValidated = $validator->validate(
        $linkedInLegacyDataResponse,
        $linkedInRequest,
        'linkedin-request-1',
    );

    if (
        ($linkedInLegacyDataValidated['content']['post']['image']['imageDataUrl'] ?? null) !==
            'data:image/png;base64,iVBORw0KGgo='
    ) {
        throw new RuntimeException('Legacy LinkedIn post base64 asset was not normalized.');
    }

    $linkedInUnexpectedVideo = $linkedInResponse;
    $linkedInUnexpectedVideo['assets']['videos'] = [['url' => 'https://example.test/video.mp4']];

    try {
        $validator->validate(
            $linkedInUnexpectedVideo,
            $linkedInRequest,
            'linkedin-request-1',
        );
        throw new RuntimeException('A video asset was accepted for a LinkedIn post.');
    } catch (GenerationException $exception) {
        if ($exception->getErrorCode() !== 'N8N_RESPONSE_INVALID') {
            throw $exception;
        }
    }

    $invalidDuration = $response;
    $invalidDuration['content']['reel']['scenes'][2]['durationSeconds'] = 4;

    try {
        $validator->validate($invalidDuration, $request, 'request-1');
        throw new RuntimeException('Invalid scene-duration sum was accepted.');
    } catch (GenerationException $exception) {
        if ($exception->getErrorCode() !== 'N8N_RESPONSE_INVALID') {
            throw $exception;
        }
    }

    $invalidDirection = $response;
    unset($invalidDirection['content']['reel']['visualDirection']);

    try {
        $validator->validate($invalidDirection, $request, 'request-1');
        throw new RuntimeException('Missing visualDirection was accepted.');
    } catch (GenerationException $exception) {
        if ($exception->getErrorCode() !== 'N8N_RESPONSE_INVALID') {
            throw $exception;
        }
    }

    foreach ([
        ['imageUrl' => 'javascript:alert(1)'],
        ['imageUrl' => 'https://user:secret@example.test/image.png'],
        ['imageDataUrl' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='],
        ['imageDataUrl' => 'data:image/png;base64,bm90LXBuZw=='],
    ] as $invalidImage) {
        $invalidAsset = $response;

        foreach ($invalidImage as $key => $value) {
            $invalidAsset['content']['post']['image'][$key] = $value;
        }

        try {
            $validator->validate($invalidAsset, $request, 'request-1');
            throw new RuntimeException('Invalid generated image asset was accepted.');
        } catch (GenerationException $exception) {
            if ($exception->getErrorCode() !== 'N8N_RESPONSE_INVALID') {
                throw $exception;
            }
        }
    }

    echo "Response validator contract tests passed.\n";
}
