<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Odontograma extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'odontogramas';

    protected $fillable = [
        'historia_clinica_id',
        'created_by',
    ];

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function piezas(): HasMany
    {
        return $this->hasMany(OdontogramaPieza::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
