<?php

namespace App\Services\Comunicacion;

use App\Models\Comunicacion\Credencial;
use App\Models\Comunicacion\ImpresionCredencial;
use App\Models\Expediente\Servidor;

/**
 * Lo que lleva la credencial, leído del expediente
 * (docs/diseno-comunicacion-social.md, 6.2).
 *
 * Es lo mismo para la tarjeta, la credencial digital y lo que guarda cada
 * impresión, y por eso vive en un solo sitio: comparar lo impreso con lo de
 * hoy solo sirve si las dos cosas se arman igual.
 */
final class DatosCredencial
{
    /**
     * Lo que se compara con la última impresión, con el nombre que se le da
     * en pantalla. Nombres y apellidos cuentan como uno.
     */
    public const ETIQUETAS = [
        'nombres'             => 'Nombre',
        'apellidos'           => 'Nombre',
        'cedula'              => 'Cédula',
        'cargo'               => 'Cargo',
        'unidad'              => 'Unidad',
        'tipo_sangre'         => 'Tipo de sangre',
        'contacto_emergencia' => 'Contacto de emergencia',
        'valida_hasta'        => 'Válida hasta',
        'fotografia_id'       => 'Foto',
    ];

    /** Lo que hay que cargar del servidor para armarlos sin consultas sueltas. */
    public const RELACIONES_SERVIDOR = [
        'contratoVigente',
        'puesto.cargo',
        'unidadAdministrativa:id,nombre',
        'fotografiaVigente',
    ];

    /**
     * @return array{nombres: string, apellidos: string, cedula: ?string, cargo: ?string, unidad: ?string, tipo_sangre: ?string, contacto_emergencia: ?array{nombre: string, parentesco: ?string, telefono: ?string}, valida_hasta: ?string, fotografia_id: ?int}
     */
    public static function de(Servidor $servidor, ?Credencial $credencial = null): array
    {
        $vinculo = $servidor->contratoVigente;
        $contacto = self::texto($servidor->contacto_emergencia_nombre);

        return [
            'nombres'             => self::unir($servidor->nombre, $servidor->segundo_nombre),
            'apellidos'           => self::unir($servidor->apellido, $servidor->segundo_apellido),
            'cedula'              => $servidor->cedula,
            // El puesto y la unidad del vínculo, como el certificado laboral;
            // los del servidor solo si el vínculo no los trae.
            'cargo'               => $vinculo?->puesto?->cargo?->nombre ?? $servidor->puesto?->cargo?->nombre,
            'unidad'              => $vinculo?->unidadAdministrativa?->nombre ?? $servidor->unidadAdministrativa?->nombre,
            'tipo_sangre'         => self::texto($servidor->tipo_sangre),
            // Sin nombre no hay a quién llamar: la línea no se imprime.
            'contacto_emergencia' => $contacto === null ? null : [
                'nombre'     => $contacto,
                'parentesco' => self::texto($servidor->contacto_emergencia_parentesco),
                'telefono'   => self::texto($servidor->contacto_emergencia_telefono),
            ],
            'valida_hasta'        => $credencial?->valida_hasta?->toDateString(),
            'fotografia_id'       => $servidor->fotografiaVigente?->id,
        ];
    }

    /**
     * Qué cambió desde que se imprimió, sin repetir etiquetas. Vacío si nunca
     * se imprimió.
     *
     * @param  array<string, mixed>  $ahora
     * @return list<string>
     */
    public static function cambios(?ImpresionCredencial $impresion, array $ahora): array
    {
        if ($impresion === null) {
            return [];
        }

        $antes = $impresion->datos_impresos ?? [];
        $cambios = [];

        foreach (self::ETIQUETAS as $clave => $etiqueta) {
            // Comparación suelta a propósito: el JSON devuelve el id de la
            // foto como entero y los arreglos del contacto en otro orden.
            if (($antes[$clave] ?? null) != ($ahora[$clave] ?? null)) {
                $cambios[$etiqueta] = true;
            }
        }

        return array_keys($cambios);
    }

    private static function unir(?string ...$partes): string
    {
        return trim(implode(' ', array_filter(array_map(fn (?string $p) => self::texto($p), $partes))));
    }

    private static function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
