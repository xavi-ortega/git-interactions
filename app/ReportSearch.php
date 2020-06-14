<?php

namespace App;

use App\Repository;
use Illuminate\Database\Eloquent\Model;

class ReportSearch extends Model
{
    protected $guarded = [];

    public function repository()
    {
        return $this->belongsTo(Repository::class)->with('latest_report');
    }
}
