<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $round_id
 * @property int $user_id
 * @property int $chips
 * @property Carbon|null $eliminated_at
 */
class RoundUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'round_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chips' => 'integer',
            'eliminated_at' => 'datetime',
        ];
    }
}
