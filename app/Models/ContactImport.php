<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactImport extends Model
{
    protected $guarded = [];

    protected $hidden = ['source_path'];
}
