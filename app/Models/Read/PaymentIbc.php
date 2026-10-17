<?php

namespace App\Models\Read;

/** Read-only projection; writes continue through the original source models. */
class PaymentIbc extends \App\Models\PaymentIbc
{
    protected $table = 'payment_ibc_effective';
}
