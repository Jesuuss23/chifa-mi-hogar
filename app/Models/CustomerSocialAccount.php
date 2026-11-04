<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSocialAccount extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['provider_id'];
}
