<?php

namespace App;

use App\IssueReport;
use App\ContributorReport;
use App\PullRequestReport;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = [];

    public function issues()
    {
        return $this->hasOne(IssueReport::class);
    }

    public function pull_requests()
    {
        return $this->hasOne(PullRequestReport::class);
    }

    public function contributors()
    {
        return $this->hasOne(ContributorReport::class);
    }
}
