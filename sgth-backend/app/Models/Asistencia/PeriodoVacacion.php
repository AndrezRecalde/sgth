<?php
namespace App\Models\Asistencia;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoVacacion extends Model
{
    use HasFactory;

    protected $table = 'periodos_vacaciones';

    protected $fillable = [
        'servidor_id',
        'anio',
        'fecha_inicio_periodo',
        'fecha_fin_periodo',
        'regimen',
        'anios_antiguedad',
        'dias_generados',
        'dias_utilizados',
        'dias_vencidos',
        'dias_saldo',
        'saldo_acumulado',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_periodo' => 'date',
            'fecha_fin_periodo'    => 'date',
            'dias_generados'       => 'decimal:2',
            'dias_utilizados'      => 'decimal:2',
            'dias_vencidos'        => 'decimal:2',
            'dias_saldo'           => 'decimal:2',
            'saldo_acumulado'      => 'decimal:2',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    // ── Helpers ──────────────────────────────────────

    /**
     * Saldo = generados − gozados − vencidos, nunca negativo.
     *
     * La fórmula estaba repetida en cada sitio que tocaba un período. Con los
     * días vencidos por el tope había que cambiarla en todos, y uno que se
     * olvidara devolvía en silencio días ya perdidos.
     *
     * Aquí estaban también `limiteAcumulacion()` —60 en LOSEP, 999 en el resto—,
     * `porcentajeUso()` y `debeAlertarLosep()`: ninguna tenía un solo llamador,
     * y las dos últimas daban una cifra falsa para el Código del Trabajo. El
     * tope, con el de cada régimen, vive en `TopeAcumulacionService`.
     */
    public function recalcularSaldo(): void
    {
        $this->dias_saldo = max(
            0,
            (float) $this->dias_generados - (float) $this->dias_utilizados - (float) $this->dias_vencidos
        );
    }
}
