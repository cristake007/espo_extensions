<?php

namespace Espo\Modules\ManagementAngajati\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\AcordariAnualeConcediu;

class AcordaZileConcediuAnuale implements JobDataLess
{
    public function __construct(private AcordariAnualeConcediu $acordari) {}

    public function run(): void
    {
        $this->acordari->acordaScadente();
    }
}
