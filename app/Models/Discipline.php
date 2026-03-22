<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Discipline extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'icon',
        'color',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
            $model->slug = $model->slug ?? Str::slug($model->name);
        });

        static::updating(function ($model) {
            if ($model->isDirty('name') && ! $model->isDirty('slug')) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function legislations(): BelongsToMany
    {
        return $this->belongsToMany(Legislation::class, 'discipline_legislation');
    }
}
