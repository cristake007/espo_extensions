<?php

// Bootstrap for unit tests.

$incarcatorSite = require "site/vendor/autoload.php";
$incarcatorProiect = require "vendor/autoload.php";

// PHPUnit preincarca autoloader-ul proiectului. Il readucem in fata celui din
// site pentru ca testele unitare sa foloseasca sursele curente din src.
$incarcatorProiect->unregister();
$incarcatorProiect->register(true);
