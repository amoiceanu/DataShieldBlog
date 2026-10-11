<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behat\FeatureContext;

$suite = new Suite('default');
$suite->withPaths('%paths.base%/features')
    ->withContexts(FeatureContext::class);

$profile = new Profile('default');
$profile->withSuite($suite);

$config = new Config();

return $config->withProfile($profile);
