<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormUser extends Model
{
    use HasFactory;

    protected $table = 'form_user';

    protected $fillable = [
        'form_id',
        'user_id',
        'can_edit',
        'status',
        'last_activity',
    ];
}
