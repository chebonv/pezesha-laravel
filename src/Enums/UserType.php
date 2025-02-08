<?php

namespace Chebon\PezeshaLaravel\Enums;

enum UserType: string
{
    case BORROWER = 'borrower';
    case MERCHANT = 'merchant';
    case AGENT = 'agent';
} 