<?php

namespace App\Models\Read;

/** Read-only projection; writes continue through the original source models. */
class PaymentPln extends \App\Models\PaymentPln
{
    protected $table = 'payment_pln_effective';
}
