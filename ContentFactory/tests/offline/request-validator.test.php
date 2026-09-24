<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Metadata
    {
        public function get(array $path): array
        {
            return [
                'schemaVersion' => 1,
                'platforms' => [[
                    'id' => 'linkedin',
                    'formats' => ['post'],
                ]],
                'formats' => [
                    'post' => ['entityValue' => 'Post'],
                ],
                'styles' => [[
                    'id' => 'automatic',
                    'entityValue' => 'Automatic',
                ]],
            ];
        }
    }
}

namespace {
    use Espo\Core\Utils\Metadata;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\ContractRegistry;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\RequestValidator;

    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/ContentFactory';
    require $module . '/Tools/ContentFactory/Generation/GenerationException.php';
    require $module . '/Tools/ContentFactory/Generation/GenerationRequest.php';
    require $module . '/Tools/ContentFactory/Validation/ContractRegistry.php';
    require $module . '/Tools/ContentFactory/Validation/RequestValidator.php';

    $validator = new RequestValidator(new ContractRegistry(new Metadata()));

    function request(string $contentType, mixed $course = null): \stdClass
    {
        return (object) [
            'brief' => 'Brief valid',
            'platform' => 'linkedin',
            'format' => 'post',
            'style' => 'automatic',
            'customInstructions' => '',
            'locale' => 'ro',
            'contentType' => $contentType,
            'course' => $course,
        ];
    }

    function expectInvalid(callable $operation): void
    {
        try {
            $operation();
            throw new \RuntimeException('Expected invalid request.');
        } catch (GenerationException $exception) {
            if ($exception->getErrorCode() !== 'INVALID_REQUEST') {
                throw $exception;
            }
        }
    }

    foreach (['general', 'webinar', 'top10', 'service', 'postEvent'] as $contentType) {
        $validated = $validator->validate(request($contentType));

        if ($validated->contentType !== $contentType || $validated->course !== null) {
            throw new \RuntimeException("Non-course state was not normalized for $contentType.");
        }

        $payload = $validated->toN8nPayload('request-id');

        if ($payload['contentType'] !== $contentType || $payload['course'] !== null) {
            throw new \RuntimeException("Non-course n8n payload is incorrect for $contentType.");
        }

        expectInvalid(static fn () => $validator->validate(request($contentType, (object) [
            'id' => 'stale-course',
        ])));
    }

    expectInvalid(static fn () => $validator->validate(request('course')));

    $course = $validator->validate(request('course', (object) [
        'id' => '36222',
        'name' => 'ISO 42001 Auditor',
        'slug' => null,
        'url' => null,
    ]));

    if ($course->course !== [
        'id' => '36222',
        'name' => 'ISO 42001 Auditor',
        'slug' => null,
        'url' => null,
    ]) {
        throw new \RuntimeException('Course state was not normalized correctly.');
    }

    $n8nPayload = $course->toN8nPayload('request-id');

    if (
        $n8nPayload['contentType'] !== 'course' ||
        $n8nPayload['course'] !== $course->course ||
        array_keys($n8nPayload) !== [
            'schemaVersion',
            'requestId',
            'contentType',
            'brief',
            'platform',
            'format',
            'style',
            'customInstructions',
            'locale',
            'course',
        ]
    ) {
        throw new \RuntimeException('The versioned n8n request contract is incorrect.');
    }

    $missingContentType = request('general');
    unset($missingContentType->contentType);
    expectInvalid(static fn () => $validator->validate($missingContentType));

    echo "Content type and course request-validation tests passed.\n";
}
