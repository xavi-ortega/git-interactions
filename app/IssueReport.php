<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IssueReport extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'id', 'report_id', 'created_at', 'updated_at'
    ];
}
