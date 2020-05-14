<?php

namespace App;

use App\Report;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    protected $guarded = [];

    protected $hidden = ['raw'];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
