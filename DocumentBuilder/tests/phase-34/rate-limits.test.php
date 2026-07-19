<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\FilePdfPreviewConcurrency;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\FilePreviewRateLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewMode;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRateLimitExceeded;

require dirname(__DIR__) . '/bootstrap.php';
$module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
require "$module/Config/Settings.php";
require "$module/Config/SettingsProvider.php";
foreach (['PreviewMode.php','PreviewRateLimit.php','PreviewRateLimitExceeded.php','FilePreviewRateLimit.php',
    'PdfPreviewConcurrency.php','FilePdfPreviewConcurrency.php'] as $file) require "$module/Preview/$file";

final readonly class Phase34RateSettings implements SettingsProvider
{
    public function __construct(private Settings $settings) {}
    public function get(): Settings { return $this->settings; }
}

$settings = new Phase34RateSettings(new Settings([
    'previewRequestsPerMinute'=>1,'maxConcurrentPreviews'=>1,'renderTimeoutSeconds'=>10,
]));
$templateId = 'phase34' . bin2hex(random_bytes(6));
$limit = new FilePreviewRateLimit($settings);
$limit->consume($templateId,PreviewMode::Sample);
Assert::throws(fn () => $limit->consume($templateId,PreviewMode::Sample),PreviewRateLimitExceeded::class,'Preview request limit was not enforced.');
$ratePath = sys_get_temp_dir() . '/document-builder-preview-limits/' . hash('sha256', "$templateId:sample") . '.json';
if (is_file($ratePath)) unlink($ratePath);

$concurrency = new FilePdfPreviewConcurrency($settings);
$lease = $concurrency->enter();
Assert::throws(fn () => $concurrency->enter(),PreviewRateLimitExceeded::class,'Concurrent preview limit was not enforced.');
$concurrency->leave($lease);
$secondLease = $concurrency->enter();
$concurrency->leave($secondLease);

echo "Phase 34 request-rate and concurrency-limit tests passed.\n";
