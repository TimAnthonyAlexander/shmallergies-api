<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAllergy extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'allergy_text',
    ];

    /**
     * Get the user that owns the allergy.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
