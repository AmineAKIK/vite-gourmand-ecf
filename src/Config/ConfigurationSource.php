<?php

namespace App\Config;

enum ConfigurationSource: string
{
    case SITE_CONFIG = 'site_config';
    case ENVIRONMENT = 'environment';
    case FIXED = 'fixed';
}
