<?php

namespace App\Models;

use Database\Factories\ChipSubmissionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $round_id
 * @property int $user_id
 * @property Carbon $recorded_on
 * @property int $chips
 * @property array<int, int>|null $chip_counts
 * @property string $source
 * @property Carbon|null $submitted_at
 * @property Carbon|null $registered_at
 * @property-read Round $round
 * @property-read User $user
 */
class ChipSubmission extends Model
{
    /** @use HasFactory<ChipSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'round_id',
        'user_id',
        'recorded_on',
        'chips',
        'chip_counts',
        'source',
        'submitted_at',
        'registered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chips' => 'integer',
            'chip_counts' => 'array',
            'submitted_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): Carbon => Carbon::parse($value),
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
