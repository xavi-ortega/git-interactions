<?php

namespace App;

use App\Casts\CollectionCast;
use Illuminate\Database\Eloquent\Model;

class CodeReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'top_changed_files' => CollectionCast::class
    ];
}
