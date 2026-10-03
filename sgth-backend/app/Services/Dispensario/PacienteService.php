<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\PacienteServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Builder;

final class PacienteService implements PacienteServiceInterface
{
    /** Cuántos resultados devuelve la búsqueda por nombre, entre los dos tipos. */
    private const MAXIMO_POR_NOMBRE = 15;

    public function buscarPorCedula(string $cedula): array
    {
        $servidor = Servidor::porCedula($cedula)
            ->with(['puesto.cargo', 'unidadAdministrativa'])
            ->first();
        if ($servidor) {
            return $this->deServidor($servidor, $this->historiaDe('servidor_id', $servidor->id));
        }

        $cargaFamiliar = CargaFamiliar::where('cedula', $cedula)
            ->activos()
            ->with('servidor')
            ->first();
        if ($cargaFamiliar) {
            return $this->deFamiliar($cargaFamiliar, $this->historiaDe('carga_familiar_id', $cargaFamiliar->id));
        }

        throw new ReglaNegocioException(
            'No se encontró ningún servidor o familiar ' .
            'registrado con esa cédula. Si es un familiar, ' .
            'debe estar registrado como carga familiar con ' .
            'su número de cédula en el Expediente del servidor.',
            404
        );
    }

    /**
     * Pacientes cuyo nombre contiene todas las palabras escritas, sin importar
     * tildes ni mayúsculas, y en cualquier orden: «arroyo camila» encuentra a
     * Camila Sofía Arroyo Vera.
     *
     * Hacía falta porque con un familiar menor, quien lo trae muchas veces no
     * se sabe su cédula. Se devuelve con la misma forma que la búsqueda por
     * cédula, así que la pantalla sigue igual después de elegir.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarPorNombre(string $texto): array
    {
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $servidores = $this->conPalabras(Servidor::query(), "nombre || ' ' || apellido", $palabras)
            ->with(['puesto.cargo', 'unidadAdministrativa'])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')
            ->limit(self::MAXIMO_POR_NOMBRE)
            ->get();

        $familiares = $this->conPalabras(CargaFamiliar::activos(), "nombres || ' ' || apellidos", $palabras)
            ->with('servidor')
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('id')
            ->limit(self::MAXIMO_POR_NOMBRE)
            ->get();

        // Las historias de todos en dos consultas, no en una por resultado.
        $historiasServidor = HistoriaClinica::whereIn('servidor_id', $servidores->pluck('id'))
            ->pluck('id', 'servidor_id');
        $historiasFamiliar = HistoriaClinica::whereIn('carga_familiar_id', $familiares->pluck('id'))
            ->pluck('id', 'carga_familiar_id');

        return collect()
            ->concat($servidores->map(fn (Servidor $s) => $this->deServidor($s, $historiasServidor->get($s->id))))
            ->concat($familiares->map(fn (CargaFamiliar $f) => $this->deFamiliar($f, $historiasFamiliar->get($f->id))))
            ->take(self::MAXIMO_POR_NOMBRE)
            ->values()
            ->all();
    }

    /** Cada palabra tiene que aparecer en el nombre completo. */
    private function conPalabras(Builder $query, string $nombreCompleto, array $palabras): Builder
    {
        foreach ($palabras as $palabra) {
            // `%` y `_` escritos por la persona se buscan como tales.
            $patron = '%' . addcslashes($palabra, '%_\\') . '%';
            $query->whereRaw("unaccent({$nombreCompleto}) ILIKE unaccent(?)", [$patron]);
        }

        return $query;
    }

    private function historiaDe(string $columna, int $id): ?int
    {
        return HistoriaClinica::where($columna, $id)->value('id');
    }

    private function deServidor(Servidor $servidor, ?int $historiaId): array
    {
        return [
            'tipo'                   => 'servidor',
            'id'                     => $servidor->id,
            'cedula'                 => $servidor->cedula,
            'nombre_completo'        => trim("{$servidor->nombre} {$servidor->apellido}"),
            'puesto'                 => $servidor->puesto?->cargo?->nombre,
            'unidad_administrativa'  => $servidor->unidadAdministrativa?->nombre,
            'tiene_historia_clinica' => $historiaId !== null,
            'historia_clinica_id'    => $historiaId,
        ];
    }

    private function deFamiliar(CargaFamiliar $familiar, ?int $historiaId): array
    {
        return [
            'tipo'                   => 'beneficiario',
            'id'                     => $familiar->id,
            'cedula'                 => $familiar->cedula,
            'nombre_completo'        => trim("{$familiar->nombres} {$familiar->apellidos}"),
            'tipo_familiar'          => $familiar->parentesco?->value
                ?? (string) $familiar->parentesco,
            'servidor_titular'       => $familiar->servidor
                ? trim("{$familiar->servidor->nombre} {$familiar->servidor->apellido}")
                : null,
            'tiene_historia_clinica' => $historiaId !== null,
            'historia_clinica_id'    => $historiaId,
        ];
    }
}
