<?php

namespace App\Config;

enum ConfigurationType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case EMAIL = 'email';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case ENUM = 'enum';
    case COLOR = 'color';
    case COORDINATE = 'coordinate';
    case POSTAL_CODE = 'postal_code';
    case SIRET = 'siret';
    case IBAN = 'iban';
    case BIC = 'bic';
    case STRING_LIST = 'string_list';
}
