<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SyncManager;
use RuntimeException;

final class SyncZileSarbatoare implements JobDataLess
{
    public function __construct(private SyncManager $syncManager)
    {}

    public function run(): void
    {
        $result = $this->syncManager->runAutomatic();

        if ($result->status === 'Failed') {
            throw new RuntimeException($result->message);
        }
    }
}
