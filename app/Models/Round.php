<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $starts_on
 * @property int $total_chips
 * @property string $status
 * @property-read Collection<int, User> $players
 * @property-read Collection<int, ChipSnapshot> $snapshots
 */
class Round extends Model
{
    protected $fillable = ['starts_on', 'total_chips', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'total_chips' => 'integer',
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
}
