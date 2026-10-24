<?php

namespace App\Services\Dispensario\Reportes;

use App\Models\Dispensario\OrdenExamen;
use Illuminate\Support\Facades\DB;

/**
 * Las órdenes de exámenes vencidas hoy (6.5 de docs/diseno-examenes-medicos.md):
 * siguen esperando resultados y pasaron los 90 días (D5). Es la lista para
 * buscar a los pacientes que no volvieron, con su teléfono —el del titular, si
 * es un familiar— y los exámenes que faltan.
 *
 * Es una foto de hoy: una orden se vence con el paso de los días, no en una
 * fecha del período. Es nominal —nombres y exámenes pedidos—, así que la
 * autoridad no la ve; cada médico ve las órdenes que pidió.
 */
final class OrdenesVencidasReporte extends ReporteBase
{
    private const TIPO_PACIENTE = ['servidor' => 'Servidor', 'familiar' => 'Familiar', 'candidato' => 'Candidato'];

    public function clave(): string { return 'examenes_vencidas'; }

    public function titulo(): string { return 'Órdenes vencidas'; }

    public function area(): string { return 'Exámenes'; }

    public function descripcion(): string
    {
        return 'Las órdenes de más de 90 días que siguen sin resultados, con el contacto del paciente para buscarlo.';
    }

    public function nominal(): bool { return true; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::MEDICO, AlcanceReporte::ODONTOLOGO];
    }

    public function filtros(): array
    {
        return ['profesional'];
    }

    public function usaPeriodo(): bool
    {
        return false;
    }

    public function corte(): ?string
    {
        return 'Órdenes vencidas';
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([
            ['folio', 'Orden'], ['fecha', 'Pedida el'], ['dias', 'Días'], ['cedula', 'Cédula'],
            ['paciente', 'Paciente'], ['tipo_paciente', 'Tipo'], ['unidad', 'Unidad administrativa'],
            ['telefono', 'Teléfono'], ['pendientes', 'Exámenes sin resultado'], ['profesional', 'Pidió'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        // Lo propio manda sobre el filtro, como en los demás reportes.
        $profesional = $alcance->profesionalId ?? $filtros->profesionalId;
        $hoy = today();

        // Un aspirante del preocupacional solo consta por su cédula en postulantes.
        $candidatos = DB::table('postulantes')
            ->selectRaw('DISTINCT ON (cedula) cedula, nombres, apellidos')
            ->orderBy('cedula')->orderByDesc('id');

        $ordenes = DB::table('ordenes_examen as o')
            ->join('historias_clinicas as hc', 'hc.id', '=', 'o.historia_clinica_id')
            ->leftJoin('servidores as s', 's.id', '=', 'hc.servidor_id')
            ->leftJoin('cargas_familiares as cf', 'cf.id', '=', 'hc.carga_familiar_id')
            ->leftJoin('servidores as tit', 'tit.id', '=', 'cf.servidor_id')
            ->leftJoinSub($candidatos, 'p', 'p.cedula', '=', 'hc.cedula_paciente')
            ->leftJoin('unidades_administrativas as ua', 'ua.id', '=',
                DB::raw('COALESCE(s.unidad_administrativa_id, tit.unidad_administrativa_id)'))
            ->whereIn('o.estado', ['emitida', 'parcial'])
            ->where('o.fecha_solicitud', '<', $hoy->copy()->subDays(OrdenExamen::DIAS_VIGENCIA)->toDateString())
            ->when($profesional, fn ($q) => $q->where('o.solicitado_por', $profesional))
            // Las más viejas primero: son las que más se alejan.
            ->orderBy('o.fecha_solicitud')->orderBy('o.id')
            ->get([
                'o.id', 'o.folio', 'o.fecha_solicitud', 'o.solicitado_por', 'hc.tipo_paciente', 'ua.nombre as unidad',
                DB::raw('COALESCE(s.cedula, cf.cedula, hc.cedula_paciente) as cedula'),
                DB::raw("TRIM(COALESCE(s.nombre || ' ' || s.apellido, cf.nombres || ' ' || cf.apellidos, p.nombres || ' ' || p.apellidos, '')) as paciente"),
                DB::raw('COALESCE(s.telefono_celular, s.telefono_convencional, tit.telefono_celular, tit.telefono_convencional) as telefono'),
            ]);

        // Los exámenes que faltan de todas, en una consulta.
        $pendientes = DB::table('orden_examen_items as i')
            ->join('catalogo_examenes as e', 'e.id', '=', 'i.catalogo_examen_id')
            ->whereIn('i.orden_examen_id', $ordenes->pluck('id'))
            ->where('i.estado', 'pendiente')
            ->orderBy('i.id')
            ->get(['i.orden_examen_id', 'e.nombre'])
            ->groupBy('orden_examen_id')
            ->map(fn ($grupo) => $grupo->pluck('nombre')->implode(', '));

        $nombres = $this->nombresDeUsuarios($ordenes->pluck('solicitado_por')->unique()->all());

        return $ordenes->map(fn ($o) => [
            'folio'         => $o->folio,
            'fecha'         => substr((string) $o->fecha_solicitud, 0, 10),
            'dias'          => (int) $hoy->diffInDays(substr((string) $o->fecha_solicitud, 0, 10), true),
            'cedula'        => $o->cedula,
            'paciente'      => $o->paciente ?: null,
            'tipo_paciente' => self::TIPO_PACIENTE[$o->tipo_paciente] ?? $o->tipo_paciente,
            'unidad'        => $o->unidad,
            'telefono'      => $o->telefono,
            'pendientes'    => $pendientes[$o->id] ?? null,
            'profesional'   => $nombres[$o->solicitado_por] ?? null,
        ])->values()->all();
    }
}
