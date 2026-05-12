<?php

namespace App\Models\Expediente;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(\App\Observers\DocumentoServidorObserver::class)]
class DocumentoServidor extends Model
{
    use SoftDeletes;

    protected $table = 'documentos_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo',
        'tamanio_bytes',
        'mime_type',
        'fecha_vencimiento',
        'descripcion',
        'estado',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'estado'            => 'boolean',
            'tamanio_bytes'     => 'integer',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
