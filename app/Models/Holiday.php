<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'date',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Whether this holiday is in the future (or today).
     */
    public function isUpcoming(): bool
    {
        return $this->date->startOfDay()->greaterThanOrEqualTo(now()->startOfDay());
    }
}
