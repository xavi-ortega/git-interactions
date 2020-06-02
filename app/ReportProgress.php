<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportProgress extends Model
{
    protected $table = 'reports_progress';

    public $timestamps = false;

    protected $guarded = [];
}
