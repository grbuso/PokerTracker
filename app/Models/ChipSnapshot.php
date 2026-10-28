<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChipSnapshot extends Model
{
    protected $fillable = ['round_id', 'user_id', 'recorded_on', 'chips'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chips' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
