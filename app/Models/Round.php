<?php

namespace App\Models;

use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $starts_on
 * @property int $total_chips
 * @property int|null $chips_per_player
 * @property string $status
 * @property-read Collection<int, User> $players
 * @property-read Collection<int, ChipSnapshot> $snapshots
 * @property-read Collection<int, ChipSubmission> $submissions
 */
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    protected $fillable = ['starts_on', 'total_chips', 'chips_per_player', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'total_chips' => 'integer',
            'chips_per_player' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, RoundUser>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(RoundUser::class)
            ->withPivot('chips', 'eliminated_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ChipSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ChipSnapshot::class);
    }

    /**
     * @return HasMany<ChipSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ChipSubmission::class);
    }
}
