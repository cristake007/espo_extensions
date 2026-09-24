<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Validation;

use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationRequest;

final readonly class ResponseValidator
{
    private const CONTENT_KEYS = [
        'post' => ['post'],
        'reel' => ['reel'],
        'video' => ['video'],
        'short' => ['short'],
        'carousel' => ['carousel'],
        'post-reel' => ['post', 'reel'],
        'video-short' => ['video', 'short'],
    ];

    public function __construct(private ContractRegistry $registry)
    {}

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public function validate(
        array $response,
        GenerationRequest $request,
        string $requestId,
    ): array {
        if (($response['success'] ?? null) === false) {
            $this->exactKeys($response, ['schemaVersion', 'requestId', 'success', 'error']);
            $error = $this->map($response['error'] ?? null);
            $this->exactKeys($error, ['code', 'message']);

            if (
                ($response['schemaVersion'] ?? null) !== 1 ||
                ($response['requestId'] ?? null) !== $requestId
            ) {
                $this->invalid();
            }

            $this->text($error['code'] ?? null, 1, 100);
            $this->text($error['message'] ?? null, 1, 1000);

            throw new GenerationException(
                'N8N_GENERATION_FAILED',
                502,
                'Serviciul extern nu a putut genera conținutul.',
            );
        }

        $response = $this->normalizePostImageAsset($response, $request);

        $this->exactKeys($response, [
            'schemaVersion',
            'requestId',
            'success',
            'platform',
            'format',
            'locale',
            'content',
        ]);

        if (
            ($response['schemaVersion'] ?? null) !== 1 ||
            ($response['requestId'] ?? null) !== $requestId ||
            ($response['success'] ?? null) !== true ||
            ($response['platform'] ?? null) !== $request->platform ||
            ($response['format'] ?? null) !== $request->format ||
            ($response['locale'] ?? null) !== $request->locale
        ) {
            $this->invalid();
        }

        $content = $this->map($response['content'] ?? null);
        $expectedKeys = self::CONTENT_KEYS[$request->format] ?? null;

        if ($expectedKeys === null) {
            $this->invalid();
        }

        $this->exactKeys($content, $expectedKeys);

        if (isset($content['post'])) {
            $this->validatePost($this->map($content['post']));
        }

        if (isset($content['carousel'])) {
            $this->validateCarousel($this->map($content['carousel']));
        }

        foreach (['reel', 'video', 'short'] as $asset) {
            if (isset($content[$asset])) {
                $this->validateVideo(
                    $this->map($content[$asset]),
                    $request->platform,
                    $asset,
                );
            }
        }

        if (isset($content['post'], $content['reel'])) {
            $post = $this->map($content['post']);
            $reel = $this->map($content['reel']);

            if ($this->normalized($post['text']) === $this->normalized($reel['caption'])) {
                $this->invalid();
            }
        }

        return $response;
    }

    /**
     * Converts the n8n asset envelope to the canonical post.image contract.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalizePostImageAsset(
        array $response,
        GenerationRequest $request,
    ): array {
        if (!array_key_exists('assets', $response)) {
            return $response;
        }

        if (!in_array($request->format, ['post', 'post-reel'], true)) {
            $this->invalid();
        }

        $assets = $this->map($response['assets']);
        $this->requiredAndOptionalKeys($assets, ['images'], ['videos']);

        if (array_key_exists('videos', $assets)) {
            $this->list($assets['videos'], 0, 0);
        }

        $images = $this->list($assets['images'] ?? null, 1, 10);
        $content = $this->map($response['content'] ?? null);
        $post = $this->map($content['post'] ?? null);
        $image = $this->map($post['image'] ?? null);

        if (
            !array_key_exists('imageDataUrl', $image) &&
            !array_key_exists('imageUrl', $image)
        ) {
            [$field, $value] = $this->generatedImageAsset($images[0]);
            $image[$field] = $value;
            $post['image'] = $image;
            $content['post'] = $post;
            $response['content'] = $content;
        }

        unset($response['assets']);

        return $response;
    }

    /** @return array{0: 'imageDataUrl'|'imageUrl', 1: string} */
    private function generatedImageAsset(mixed $asset): array
    {
        if (is_string($asset)) {
            return $this->classifyGeneratedImageValue($asset);
        }

        $data = $this->map($asset);

        foreach (['imageDataUrl', 'dataUrl', 'imageUrl', 'url'] as $field) {
            if (is_string($data[$field] ?? null) && $data[$field] !== '') {
                return $this->classifyGeneratedImageValue($data[$field]);
            }
        }

        if (is_string($data['data'] ?? null)) {
            $value = $data['data'];

            if (
                str_starts_with(strtolower($value), 'data:image/') ||
                preg_match('/\Ahttps?:\/\//i', $value) === 1
            ) {
                return $this->classifyGeneratedImageValue($value);
            }
        }

        $payload = $data['dataBase64'] ?? $data['base64'] ?? $data['data'] ?? null;
        $mimeType = $data['mimeType'] ?? $data['contentType'] ?? null;

        if (
            is_string($payload) &&
            $payload !== '' &&
            is_string($mimeType) &&
            in_array(strtolower($mimeType), [
                'image/png',
                'image/jpeg',
                'image/webp',
                'image/gif',
            ], true)
        ) {
            return ['imageDataUrl', sprintf(
                'data:%s;base64,%s',
                strtolower($mimeType),
                $payload,
            )];
        }

        $this->invalid();
    }

    /** @return array{0: 'imageDataUrl'|'imageUrl', 1: string} */
    private function classifyGeneratedImageValue(string $value): array
    {
        if (str_starts_with(strtolower($value), 'data:image/')) {
            return ['imageDataUrl', $value];
        }

        if (preg_match('/\Ahttps?:\/\//i', $value) === 1) {
            return ['imageUrl', $value];
        }

        $this->invalid();
    }

    /** @param array<string, mixed> $post */
    private function validatePost(array $post): void
    {
        $this->requiredAndOptionalKeys(
            $post,
            ['headline', 'text', 'cta', 'image'],
            ['hashtags'],
        );
        $this->text($post['headline'] ?? null, 1, 300);
        $this->text($post['text'] ?? null, 1, 10000);
        $this->text($post['cta'] ?? null, 1, 300);

        if (array_key_exists('hashtags', $post)) {
            $this->hashtags($post['hashtags']);
        }

        $image = $this->map($post['image'] ?? null);
        $this->requiredAndOptionalKeys(
            $image,
            [
                'aspectRatio',
                'width',
                'height',
                'safeArea',
                'visualPrompt',
                'headline',
                'supportingText',
            ],
            ['imageUrl', 'imageDataUrl'],
        );
        $this->mediaProfile($image, ['aspectRatio' => '4:5', 'width' => 1080, 'height' => 1350]);
        $this->safeArea($this->map($image['safeArea'] ?? null));
        $this->text($image['visualPrompt'] ?? null, 40, 4000);
        $this->text($image['headline'] ?? null, 1, 80);
        $this->text($image['supportingText'] ?? null, 0, 120);

        if (array_key_exists('imageUrl', $image)) {
            $this->imageUrl($image['imageUrl']);
        }

        if (array_key_exists('imageDataUrl', $image)) {
            $this->imageDataUrl($image['imageDataUrl']);
        }

        $postText = $this->normalized($post['text']);

        if (
            $postText === $this->normalized($image['headline']) ||
            ($image['supportingText'] !== '' && $postText === $this->normalized($image['supportingText']))
        ) {
            $this->invalid();
        }
    }

    /** @param array<string, mixed> $carousel */
    private function validateCarousel(array $carousel): void
    {
        $this->exactKeys($carousel, [
            'caption',
            'cta',
            'hashtags',
            'aspectRatio',
            'width',
            'height',
            'safeArea',
            'visualDirection',
            'slides',
        ]);
        $this->text($carousel['caption'] ?? null, 1, 5000);
        $this->text($carousel['cta'] ?? null, 1, 300);
        $this->hashtags($carousel['hashtags'] ?? null);
        $this->mediaProfile($carousel, ['aspectRatio' => '4:5', 'width' => 1080, 'height' => 1350]);
        $this->safeArea($this->map($carousel['safeArea'] ?? null));
        $this->visualDirection($this->map($carousel['visualDirection'] ?? null));

        $slides = $this->list($carousel['slides'] ?? null, 3, 10);

        foreach ($slides as $index => $slideValue) {
            $slide = $this->map($slideValue);
            $this->exactKeys($slide, ['order', 'role', 'title', 'text', 'visualPrompt']);

            if (($slide['order'] ?? null) !== $index + 1) {
                $this->invalid();
            }

            $expectedRole = match (true) {
                $index === 0 => 'hook',
                $index === count($slides) - 1 => 'cta',
                default => 'content',
            };

            if (($slide['role'] ?? null) !== $expectedRole) {
                $this->invalid();
            }

            $this->text($slide['title'] ?? null, 1, 120);
            $this->text($slide['text'] ?? null, 1, 350);
            $this->text($slide['visualPrompt'] ?? null, 20, 4000);
        }
    }

    /** @param array<string, mixed> $video */
    private function validateVideo(array $video, string $platform, string $asset): void
    {
        $this->exactKeys($video, [
            'hook',
            'durationSeconds',
            'aspectRatio',
            'width',
            'height',
            'caption',
            'cta',
            'hashtags',
            'visualDirection',
            'scenes',
        ]);
        $this->text($video['hook'] ?? null, 1, 300);
        $duration = $this->integer($video['durationSeconds'] ?? null, 15, 30);
        $this->text($video['caption'] ?? null, 1, 5000);
        $this->text($video['cta'] ?? null, 1, 300);
        $this->hashtags($video['hashtags'] ?? null);
        $this->visualDirection($this->map($video['visualDirection'] ?? null));

        $profile = $this->registry->videoProfile($platform, $asset);

        if ($profile === null) {
            $this->invalid();
        }

        $this->mediaProfile($video, $profile);
        $scenes = $this->list($video['scenes'] ?? null, 3, 6);
        $sceneDuration = 0;

        foreach ($scenes as $index => $sceneValue) {
            $scene = $this->map($sceneValue);
            $this->exactKeys($scene, [
                'order',
                'durationSeconds',
                'visualPrompt',
                'voiceover',
                'onScreenText',
            ]);

            if (($scene['order'] ?? null) !== $index + 1) {
                $this->invalid();
            }

            $sceneDuration += $this->integer($scene['durationSeconds'] ?? null, 3, 8);
            $this->text($scene['visualPrompt'] ?? null, 20, 4000);
            $this->text($scene['voiceover'] ?? null, 1, 1000);
            $this->text($scene['onScreenText'] ?? null, 1, 120);
        }

        if ($sceneDuration !== $duration) {
            $this->invalid();
        }
    }

    /** @param array<string, mixed> $direction */
    private function visualDirection(array $direction): void
    {
        $this->exactKeys($direction, ['stylePrompt', 'continuityPrompt', 'negativePrompt']);
        $this->text($direction['stylePrompt'] ?? null, 40, 4000);
        $this->text($direction['continuityPrompt'] ?? null, 20, 2000);
        $this->text($direction['negativePrompt'] ?? null, 10, 2000);
    }

    /** @param array<string, mixed> $safeArea */
    private function safeArea(array $safeArea): void
    {
        $this->exactKeys($safeArea, [
            'topPercent',
            'rightPercent',
            'bottomPercent',
            'leftPercent',
        ]);

        foreach ($safeArea as $value) {
            if ($value !== 8) {
                $this->invalid();
            }
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param array{aspectRatio: string, width: int, height: int} $profile
     */
    private function mediaProfile(array $value, array $profile): void
    {
        if (
            ($value['aspectRatio'] ?? null) !== $profile['aspectRatio'] ||
            ($value['width'] ?? null) !== $profile['width'] ||
            ($value['height'] ?? null) !== $profile['height']
        ) {
            $this->invalid();
        }
    }

    private function hashtags(mixed $value): void
    {
        $hashtags = $this->list($value, 0, 30);

        if (count(array_unique($hashtags, SORT_STRING)) !== count($hashtags)) {
            $this->invalid();
        }

        foreach ($hashtags as $hashtag) {
            if (
                !is_string($hashtag) ||
                mb_strlen(trim($hashtag)) < 1 ||
                mb_strlen(trim($hashtag)) > 100
            ) {
                $this->invalid();
            }
        }
    }

    private function imageUrl(mixed $value): void
    {
        if (
            !is_string($value) ||
            $value === '' ||
            $value !== trim($value) ||
            strlen($value) > 4096 ||
            preg_match('/\s/u', $value) === 1 ||
            filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            $this->invalid();
        }

        $parts = parse_url($value);

        if (
            !is_array($parts) ||
            !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) ||
            !is_string($parts['host'] ?? null) ||
            isset($parts['user']) ||
            isset($parts['pass'])
        ) {
            $this->invalid();
        }
    }

    private function imageDataUrl(mixed $value): void
    {
        if (!is_string($value) || strlen($value) > 6 * 1024 * 1024) {
            $this->invalid();
        }

        if (preg_match('/\Adata:image\/(png|jpeg|webp|gif);base64,/i', $value, $matches) !== 1) {
            $this->invalid();
        }

        $payload = substr($value, strlen($matches[0]));

        if ($payload === '' || strlen($payload) % 4 !== 0) {
            $this->invalid();
        }

        $decoded = base64_decode($payload, true);

        if (!is_string($decoded) || !$this->matchesImageType(strtolower($matches[1]), $decoded)) {
            $this->invalid();
        }
    }

    private function matchesImageType(string $type, string $data): bool
    {
        return match ($type) {
            'png' => str_starts_with($data, "\x89PNG\r\n\x1a\n"),
            'jpeg' => str_starts_with($data, "\xff\xd8\xff"),
            'gif' => str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a'),
            'webp' => str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP',
            default => false,
        };
    }

    private function text(mixed $value, int $minimum, int $maximum): string
    {
        if (!is_string($value)) {
            $this->invalid();
        }

        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < $minimum || $length > $maximum) {
            $this->invalid();
        }

        return $trimmed;
    }

    private function integer(mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            $this->invalid();
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            $this->invalid();
        }

        return $value;
    }

    /** @return list<mixed> */
    private function list(mixed $value, int $minimum, int $maximum): array
    {
        if (
            !is_array($value) ||
            !array_is_list($value) ||
            count($value) < $minimum ||
            count($value) > $maximum
        ) {
            $this->invalid();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $expected
     */
    private function exactKeys(array $value, array $expected): void
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);

        if ($keys !== $expected) {
            $this->invalid();
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $required
     * @param list<string> $optional
     */
    private function requiredAndOptionalKeys(
        array $value,
        array $required,
        array $optional,
    ): void {
        $keys = array_keys($value);

        if (
            array_diff($required, $keys) !== [] ||
            array_diff($keys, [...$required, ...$optional]) !== []
        ) {
            $this->invalid();
        }
    }

    private function normalized(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function invalid(): never
    {
        throw new GenerationException(
            'N8N_RESPONSE_INVALID',
            502,
            'Răspunsul serviciului de generare nu este valid.',
        );
    }
}
