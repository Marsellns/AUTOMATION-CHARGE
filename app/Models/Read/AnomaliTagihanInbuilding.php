<?php

namespace App\Models\Read;

/** Read-only projection; writes continue through the original source models. */
class AnomaliTagihanInbuilding extends \App\Models\AnomaliTagihanInbuilding
{
    protected $table = 'anomali_tagihan_inbuilding_effective';
}
