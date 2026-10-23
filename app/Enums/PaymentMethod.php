<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case ManualTransfer = 'manual_transfer';
    case PaymentGateway = 'payment_gateway';
}
