<?php

namespace App;

use App\Report;
use Illuminate\Database\Eloquent\Model;

class ReportProgress extends Model
{
    protected $table = 'reports_progress';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['report_id'];

    public function report()
    {
        return $this->belongsTo(Report::class)->with('repository');
    }
}
