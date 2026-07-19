<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;

final readonly class ConfiguredStyleResolver implements ResolvedStyleProvider
{
    private StyleResolver $resolver;

    public function __construct(Settings $settings)
    {
        $this->resolver = new StyleResolver($settings->allowedFontList(), $settings->defaultFont());
    }

    public function resolve(array $defaults, array ...$layers): array
    {
        return $this->resolver->resolve($defaults, ...$layers);
    }
}
