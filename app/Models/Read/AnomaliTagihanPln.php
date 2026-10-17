<?php

namespace App\Models\Read;

/** Read-only projection; writes continue through the original source models. */
class AnomaliTagihanPln extends \App\Models\AnomaliTagihanPln
{
    protected $table = 'anomali_tagihan_pln_effective';
}
