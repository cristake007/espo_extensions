<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\EspoHttpTransport;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\EspoHolidayStore;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayProvider;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayStore;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HttpTransport;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\IntegrationSyncLock;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\NagerDateClient;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SyncLock;

final class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder->bindImplementation(HttpTransport::class, EspoHttpTransport::class);
        $binder->bindImplementation(HolidayProvider::class, NagerDateClient::class);
        $binder->bindImplementation(HolidayStore::class, EspoHolidayStore::class);
        $binder->bindImplementation(SyncLock::class, IntegrationSyncLock::class);
    }
}
