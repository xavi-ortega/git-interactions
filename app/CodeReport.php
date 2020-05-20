<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'files' => 'array'
    ];
}
