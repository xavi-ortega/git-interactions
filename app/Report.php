<?php

namespace App;

use App\Repository;
use App\CodeReport;
use App\IssueReport;
use App\ContributorReport;
use App\PullRequestReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Report extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'repository_id', 'user_id', 'deleted_at', 'updated_at'
    ];

    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

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

    public function code()
    {
        return $this->hasOne(CodeReport::class);
    }

    public function progress()
    {
        return $this->hasOne(ReportProgress::class);
    }
}
