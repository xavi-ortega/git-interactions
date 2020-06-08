<?php

namespace App;

use App\Casts\CollectionCast;
use Illuminate\Database\Eloquent\Model;

class CodeReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'branches' => CollectionCast::class,
        'top_changed_files' => CollectionCast::class
    ];

    protected $hidden = [
        'id', 'report_id', 'created_at', 'updated_at'
    ];
}
