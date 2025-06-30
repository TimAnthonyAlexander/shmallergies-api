<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'upc_code',
        'ingredient_image_path',
    ];

    /**
     * Get the ingredients for the product.
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
} 