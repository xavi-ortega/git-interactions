<?php

namespace App\Helpers\Constants;

abstract class ReportProgressType
{
    const WAITING = 0;
    const FETCHING_ISSUES = 1;
    const FETCHING_PULL_REQUESTS = 2;
    const FETCHING_CONTRIBUTORS = 3;
    const FETCHING_CODE = 4;
}
