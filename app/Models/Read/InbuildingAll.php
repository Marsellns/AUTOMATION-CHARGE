<?php

namespace App\Models\Read;

/** Read-only projection; writes continue through the original source models. */
class InbuildingAll extends \App\Models\InbuildingAll
{
    protected $table = 'inbuilding_all_effective';
}
