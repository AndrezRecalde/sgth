# SGTH — Sistema de Gestión de Talento Humano
# Contexto del Agente — Archivo 03: Convenciones de Base de Datos

---

## REGLA PRINCIPAL

> Todas las tablas propias del sistema van en **español, snake_case, plural**.
> Las tablas nativas de Laravel permanecen en inglés tal como las genera el framework.

---

## TABLAS NATIVAS DE LARAVEL — MANTENER EN INGLÉS

Estas tablas NO se renombran nunca:
```
users
password_reset_tokens
personal_access_tokens
jobs
job_batches
failed_jobs
cache
cache_locks
sessions
migrations
```

---

## TABLAS DEL SISTEMA — EN ESPAÑOL

### Auth y administración
```
usuarios                     ← extiende users con campos adicionales del servidor
roles                        ← gestionado por Spatie Permission
permisos                     ← gestionado por Spatie Permission
role_usuario                 ← tabla pivote rol-usuario
permiso_rol                  ← tabla pivote permiso-rol
activity_log                 ← gestionado por Spatie Activitylog
```

### Módulo 01 — Estructura
```
unidades_administrativas
puestos
valoraciones_puesto
```

### Módulo 02 — Expediente
```
servidores
documentos_servidor
movimientos_personal
```

### Módulo 03 — Nómina
```
nominas
conceptos_nomina
detalle_nomina               ← SIN SoftDeletes (registro inmutable)
roles_pago
handoffs_erp                 ← SIN SoftDeletes (registro inmutable de auditoría)
```

### Módulo 04 — Asistencia
```
marcaciones                  ← SIN SoftDeletes (registro biométrico inmutable)
permisos_servidor            ← nombre completo para evitar conflicto con tabla Spatie
folios_permiso               ← SIN SoftDeletes (folio generado es inmutable)
vacaciones
licencias
```

### Módulo 05 — SGD
```
documentos_institucionales
expedientes_electronicos
tramites
series_documentales
retenciones_documentales
```

### Módulo 06 — Autoservicio
```
-- Sin tablas propias, usa tablas de otros módulos --
```

### Módulo 07 — Selección
```
convocatorias
postulantes
evaluaciones_seleccion
onboardings
```

### Módulo 08 — Evaluación del Desempeño
```
evaluaciones_desempeno
criterios_evaluacion
resultados_evaluacion
planes_mejora
```

### Módulo 09 — Viáticos
```
viaticos
liquidaciones_viatico
tarifas_viatico
```

### Módulo 10 — SSO
```
riesgos_laborales
accidentes_trabajo
equipos_proteccion
inspecciones_sso
capacitaciones_sso
```

### Módulo 11 — Dispensario Médico
```
historias_clinicas           ← datos cifrados AES-256
consultas_medicas            ← datos cifrados AES-256
recetas_medicas
items_receta
fichas_salud_ocupacional
inventario_medicinas
movimientos_inventario_med   ← SIN SoftDeletes (kardex inmutable)
agendas_medicas
```

### Módulo 12 — Inventario TI
```
bienes_informaticos
asignaciones_bien
mantenimientos_bien
```

### Módulo 13 — Helpdesk
```
tickets
comentarios_ticket
areas_dtic
tecnicos_dtic
slas
base_conocimiento
encuestas_satisfaccion
```

### Módulo 14 — Disciplinario
```
sumarios
sanciones_disciplinarias
```

### Módulo 15 — Capacitación
```
planes_capacitacion
cursos
inscripciones_curso
certificados_capacitacion
evaluaciones_capacitacion
```

### Módulo 16 — Actividades
```
actividades_laborales
informes_actividades
```

### Módulo 17 — Bienestar
```
planes_bienestar
actividades_bienestar
encuestas_clima
resultados_clima
```

### Módulo 18 — Reportería
```
-- Sin tablas propias, usa vistas y queries sobre tablas existentes --
configuraciones_reporte      ← guarda configuraciones de reportes personalizados
```

---

## SOFT DELETES — REGLA

### Modelos QUE SÍ usan SoftDeletes
Todos los modelos principales del sistema usan `SoftDeletes` EXCEPTO los listados abajo.

```php
// Toda migración de tabla principal incluye:
$table->softDeletes();
```

### Modelos QUE NO usan SoftDeletes (registros inmutables)
```
marcaciones              ← registro biométrico — nunca se elimina
folios_permiso           ← folio generado — nunca se elimina
detalle_nomina           ← líneas de nómina cerrada — nunca se modifican
handoffs_erp             ← registro de transferencias al ERP — auditoría
movimientos_inventario_med ← kardex de medicinas — auditoría
activity_log             ← log de auditoría — nunca se elimina
```

---

## CONVENCIÓN DE MIGRACIONES

### Nombre de archivo
```
YYYY_MM_DD_HHMMSS_crear_tabla_{nombre_tabla}.php

Ejemplos:
2026_01_01_000001_crear_tabla_unidades_administrativas.php
2026_01_01_000002_crear_tabla_puestos.php
2026_01_01_000003_crear_tabla_servidores.php
2026_01_01_000010_crear_tabla_nominas.php
```

### Estructura base de una migración
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('denominacion');
            $table->foreignId('unidad_administrativa_id')
                  ->constrained('unidades_administrativas')
                  ->restrictOnDelete();
            $table->string('grupo_ocupacional', 100);
            $table->unsignedTinyInteger('grado_rmu');
            $table->decimal('rmu', 10, 2);
            $table->string('nivel', 50);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();  ← siempre al final antes de timestamps

            // Índices para columnas frecuentemente consultadas
            $table->index('estado');
            $table->index('unidad_administrativa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
```

---

## CAMPOS ESTÁNDAR POR TIPO DE TABLA

### Tabla de entidad principal
```php
$table->id();
// ... campos propios de la entidad
$table->boolean('estado')->default(true);
$table->foreignId('created_by')->nullable()->constrained('users');
$table->foreignId('updated_by')->nullable()->constrained('users');
$table->timestamps();
$table->softDeletes();
```

### Tabla de relación pivote
```php
$table->id();
$table->foreignId('entidad_a_id')->constrained('entidades_a')->cascadeOnDelete();
$table->foreignId('entidad_b_id')->constrained('entidades_b')->cascadeOnDelete();
$table->timestamps();
// Sin softDeletes en tablas pivote
```

### Tabla de registro inmutable (auditoría)
```php
$table->id();
// ... campos del registro
$table->timestamps();
// SIN softDeletes — estos registros nunca se eliminan
```

---

## SEEDERS REQUERIDOS

```
database/seeders/
  DatabaseSeeder.php          ← orquesta el orden de ejecución
  RolSeeder.php               ← crea todos los roles del Enum Rol
  PermisoSeeder.php           ← crea todos los permisos del Enum Permiso
  RolPermisoSeeder.php        ← asigna permisos a roles según matriz
  UnidadAdministrativaSeeder.php  ← unidades base del GAD
  EscalaRmuSeeder.php         ← tabla salarial del sector público ecuatoriano
  TarifaViaticoSeeder.php     ← tarifas MRL por zona y nivel
  CatalogoPermisosSeeder.php  ← tipos de permiso institucional
  AdminTiSeeder.php           ← usuario inicial admin-ti para el área TI
```

---

## API RESOURCES — TRANSFORMACIÓN DE DATOS

```php
// Siempre usar API Resources — nunca devolver el modelo directamente
<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'codigo'                   => $this->codigo,
            'denominacion'             => $this->denominacion,
            'unidad_administrativa'    => new UnidadAdministrativaResource(
                                             $this->whenLoaded('unidadAdministrativa')
                                         ),
            'grupo_ocupacional'        => $this->grupo_ocupacional,
            'grado_rmu'                => $this->grado_rmu,
            'rmu'                      => $this->rmu,
            'nivel'                    => $this->nivel,
            'estado'                   => $this->estado,
            'creado_en'                => $this->created_at?->toIso8601String(),
            'actualizado_en'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

