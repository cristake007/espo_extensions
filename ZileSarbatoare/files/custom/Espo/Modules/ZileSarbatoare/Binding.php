<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\EspoHttpTransport;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HttpTransport;

final class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder->bindImplementation(HttpTransport::class, EspoHttpTransport::class);
    }
}
