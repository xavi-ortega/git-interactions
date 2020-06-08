<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PullRequestReport extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'id', 'report_id', 'updated_at'
    ];
}
