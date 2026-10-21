<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$installed = getcwd() . '/custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php';
if (!is_file($installed)) {
    fwrite(STDERR, "Run this command from the EspoCRM application root after installing Employee Management 0.8.13.\n");
    exit(2);
}
require $installed;
