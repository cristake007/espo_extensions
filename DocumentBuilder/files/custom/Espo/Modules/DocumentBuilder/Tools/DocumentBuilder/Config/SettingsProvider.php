<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config;

interface SettingsProvider
{
    public function get(): Settings;
}
