<?php

namespace App\Config;

enum ConfigurationScope: string
{
    case SYSTEM = 'system';
    case MARKET = 'market';
    case TENANT = 'tenant';
    case OPERATOR = 'operator';
    case FUTURE_ENTITLEMENT = 'future_entitlement';
}
