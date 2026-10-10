<?php

namespace App\Services\Admin\Reports\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BuildsReportQueryCacheKey
{
    private function queryScopeSignature(Builder $scopedTickets): string
    {
        return sha1($scopedTickets->toSql().'|'.json_encode($scopedTickets->getBindings()));
    }
}
