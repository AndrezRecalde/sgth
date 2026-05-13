<?php
namespace App\Models\InventarioTi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BienInformatico extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'bienes_informaticos';
    protected $fillable = ['codigo_qr', 'codigo_institucional', 'tipo_bien_id', 'marca_id', 'origen_bien_id', 'modelo', 'numero_serie', 'estado_operativo', 'condicion_fisica', 'fecha_fin_vida_util', 'caracteristicas_tecnicas', 'created_by', 'updated_by'];
    protected function casts(): array { return ['fecha_fin_vida_util' => 'date', 'caracteristicas_tecnicas' => 'array']; }
    public function tipo() { return $this->belongsTo(TipoBien::class, 'tipo_bien_id'); }
    public function marca() { return $this->belongsTo(Marca::class, 'marca_id'); }
    public function origen() { return $this->belongsTo(OrigenBien::class, 'origen_bien_id'); }
}