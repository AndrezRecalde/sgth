<?php

namespace App\Models\Sgd;

use App\Models\User;
use App\Observers\Sgd\SerieDocumentalObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(SerieDocumentalObserver::class)]
class SerieDocumental extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'series_documentales';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
