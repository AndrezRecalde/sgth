<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila que el SGTH escribió en dbo.USER_SPEDAY de Sirha7 al aprobar un
 * certificado médico: una por día de reposo. Como las de los permisos
 * (`PermisoSirha7Fila`): para retirarlas hay que decirle al procedimiento
 * cuántas son, y si en Sirha7 encuentra otra cantidad no borra nada.
 */
class CertificadoSirha7Fila extends Model
{
    protected $table = 'certificado_sirha7_filas';

    protected $fillable = ['certificado_medico_id', 'sirha7_id', 'inicio', 'fin'];

    protected function casts(): array
    {
        return [
            'sirha7_id' => 'integer',
            'inicio'    => 'datetime',
            'fin'       => 'datetime',
        ];
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoMedico::class, 'certificado_medico_id');
    }
}
