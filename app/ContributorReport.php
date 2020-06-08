<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContributorReport extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'id', 'report_id', 'created_at', 'updated_at'
    ];
}
