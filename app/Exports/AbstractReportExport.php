<?php

namespace App\Exports;

use App\Models\AppUser;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

abstract class AbstractReportExport
{
    protected ?Carbon $from;

    protected ?Carbon $to;

    protected AppUser $actor;

    public function __construct(AppUser $actor, ?Carbon $from = null, ?Carbon $to = null)
    {
        $this->actor = $actor;
        $this->from = $from;
        $this->to = $to;

        if (! $actor->hasPermission(Permission::REPORT_EXPORT)) {
            throw new AuthorizationException('You do not have permission to export reports.');
        }
    }
}
