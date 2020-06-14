<?php

namespace App;

use App\Report;
use App\ReportSearch;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'raw', 'pointer', 'created_at', 'updated_at'
    ];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function latest_report()
    {
        return $this->hasOne(Report::class)->has('issues')->has('pull_requests')->has('contributors')->has('code')->latest();
    }

    public function searches()
    {
        return $this->hasOne(ReportSearch::class);
    }
}
