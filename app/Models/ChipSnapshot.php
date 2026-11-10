<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
     * @return Attribute<string, string>
     */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Carbon::parse($value)->toDateString(),
        );
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
