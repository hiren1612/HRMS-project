<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'body',
    ];
}
