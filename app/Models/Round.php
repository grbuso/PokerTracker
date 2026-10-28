<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    protected $fillable =['starts_on', 'total_chips', 'status'];

    public function players() {
        return $this->belongsToMany(User::class)
            ->withPivot('chips', 'eliminated_at')
            ->withTimestamps();
    }

    public function snapshots()
    {
        return $this->hasMany(ChipSnapshot::class);
    }
}
