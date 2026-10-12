<?php

namespace App\Enums;

/**
 * Las licencias con remuneración (LOSEP, Art. 27; Código del Trabajo, Art. 152
 * para los obreros; diseño de Acciones de Personal, 4.4 y 9.4; TH N12; fase
 * 5.1). Se registran en Asistencia, sin acción de personal, con su causa, su
 * tope y su respaldo, y no descuentan vacaciones.
 *
 * Hasta aquí eran «motivos» de la solicitud de vacaciones, sin tope ni
 * respaldo. La licencia sin remuneración no está aquí: es acción de personal
 * (LOSEP 28; fase 2.2). Ni la capacitación, que es comisión de servicios.
 *
 * Los topes son por licencia, en días calendario, y salen del diseño (4.4);
 * la paternidad de 15 días es la de la Ley Orgánica del Derecho al Cuidado
 * Humano (2023). Conviene que Talento Humano o jurídico revise la tabla: vive
 * aquí y en ningún otro sitio.
 *
 * `PACTADA` no es de la ley: es una causa que el contrato colectivo de los
 * obreros añade (fase 5.3). Su nombre, sus días y su base son los de la
 * cláusula (`ClausulaDeLicencia`), y sin ella no se registra. El contrato
 * también puede ampliar los días de una causa de la ley.
 */
enum CausaLicencia: string
{
    case ENFERMEDAD                = 'enfermedad';
    case ENFERMEDAD_CATASTROFICA   = 'enfermedad_catastrofica';
    case MATERNIDAD                = 'maternidad';
    case PATERNIDAD                = 'paternidad';
    case FALLECIMIENTO_DE_LA_MADRE = 'fallecimiento_de_la_madre';
    case ADOPCION                  = 'adopcion';
    case HIJOS_HOSPITALIZADOS      = 'hijos_hospitalizados';
    case CALAMIDAD_DOMESTICA       = 'calamidad_domestica';
    case MATRIMONIO                = 'matrimonio';
    case PACTADA                   = 'pactada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENFERMEDAD                => 'Enfermedad',
            self::ENFERMEDAD_CATASTROFICA   => 'Discapacidad, enfermedad catastrófica o rara, o accidente grave',
            self::MATERNIDAD                => 'Maternidad',
            self::PATERNIDAD                => 'Paternidad',
            self::FALLECIMIENTO_DE_LA_MADRE => 'Fallecimiento de la madre (licencia del padre)',
            self::ADOPCION                  => 'Adopción',
            self::HIJOS_HOSPITALIZADOS      => 'Hijos hospitalizados o con patologías degenerativas',
            self::CALAMIDAD_DOMESTICA       => 'Calamidad doméstica',
            self::MATRIMONIO                => 'Matrimonio o unión de hecho',
            self::PACTADA                   => 'Pactada en el contrato colectivo',
        };
    }

    public function baseLegal(): string
    {
        return match ($this) {
            self::ENFERMEDAD                => 'LOSEP, Art. 27 a',
            self::ENFERMEDAD_CATASTROFICA   => 'LOSEP, Art. 27 b',
            self::MATERNIDAD                => 'LOSEP, Art. 27 c; Código del Trabajo, Art. 152',
            self::PATERNIDAD                => 'LOSEP, Art. 27 d y e; Código del Trabajo, Art. 152',
            self::FALLECIMIENTO_DE_LA_MADRE => 'LOSEP, Art. 27 f; Código del Trabajo, Art. 152',
            self::ADOPCION                  => 'LOSEP, Art. 27 g; Código del Trabajo, Art. 152',
            self::HIJOS_HOSPITALIZADOS      => 'LOSEP, Art. 27 h; Código del Trabajo, Art. 152',
            self::CALAMIDAD_DOMESTICA       => 'LOSEP, Art. 27 i',
            self::MATRIMONIO                => 'LOSEP, Art. 27 j',
            self::PACTADA                   => 'Contrato colectivo',
        };
    }

    /** El tope en palabras, para el formulario. */
    public function explicacionDelTope(): string
    {
        return match ($this) {
            self::ENFERMEDAD                => 'Hasta 3 meses, y otros 3 para la rehabilitación.',
            self::ENFERMEDAD_CATASTROFICA   => 'El tiempo que diga el certificado.',
            self::MATERNIDAD                => '12 semanas; 10 días más si el parto es múltiple.',
            self::PATERNIDAD                => '15 días; más días por cesárea o parto múltiple, prematuro o enfermedad degenerativa.',
            self::FALLECIMIENTO_DE_LA_MADRE => 'Lo que reste de la licencia de maternidad.',
            self::ADOPCION                  => '30 días.',
            self::HIJOS_HOSPITALIZADOS      => '25 días.',
            self::CALAMIDAD_DOMESTICA       => '8 días por el cónyuge, los padres o los hijos; 3 por otros parientes.',
            self::MATRIMONIO                => '3 días.',
            self::PACTADA                   => 'Los días que diga su cláusula.',
        };
    }

    /**
     * Los casos que cambian el tope: en la maternidad y la paternidad suman
     * días; en la calamidad, que lo exige, lo fijan.
     *
     * @return array<string, array{etiqueta: string, dias: int}>
     */
    public function alcances(): array
    {
        return match ($this) {
            self::MATERNIDAD => [
                'parto_multiple' => ['etiqueta' => 'Parto múltiple', 'dias' => 10],
            ],
            self::PATERNIDAD => [
                'cesarea_o_multiple'      => ['etiqueta' => 'Por cesárea o parto múltiple', 'dias' => 5],
                'prematuro'               => ['etiqueta' => 'Nacimiento prematuro o en condiciones de cuidado especial', 'dias' => 8],
                'enfermedad_degenerativa' => [
                    'etiqueta' => 'Con enfermedad degenerativa, terminal o irreversible, o discapacidad severa',
                    'dias'     => 25,
                ],
            ],
            self::CALAMIDAD_DOMESTICA => [
                'pariente_cercano' => [
                    'etiqueta' => 'Del cónyuge o conviviente, los padres o los hijos, o un siniestro sobre sus bienes',
                    'dias'     => 8,
                ],
                'otro_pariente' => ['etiqueta' => 'De otros parientes hasta el segundo grado', 'dias' => 3],
            ],
            default => [],
        };
    }

    /** La calamidad dice de quién: el tope depende de eso. */
    public function exigeAlcance(): bool
    {
        return $this === self::CALAMIDAD_DOMESTICA;
    }

    /**
     * Los días que admite, con su caso. Null si no tiene tope: lo dice el
     * certificado (Art. 27 b).
     */
    public function tope(?string $alcance = null): ?int
    {
        $extra = $this->alcances()[$alcance]['dias'] ?? null;

        return match ($this) {
            self::ENFERMEDAD_CATASTROFICA   => null,
            self::CALAMIDAD_DOMESTICA       => $extra,
            self::ENFERMEDAD                => 180,
            self::MATERNIDAD                => 84 + ($extra ?? 0),
            self::PATERNIDAD                => 15 + ($extra ?? 0),
            self::FALLECIMIENTO_DE_LA_MADRE => 84,
            self::ADOPCION                  => 30,
            self::HIJOS_HOSPITALIZADOS      => 25,
            self::MATRIMONIO                => 3,
            // La de su cláusula: sin ella no hay licencia pactada.
            self::PACTADA                   => null,
        };
    }

    /**
     * Quién la aprueba y la registra en Sirha7: las de salud y la calamidad,
     * Trabajo Social, como en los permisos (decisión del 2026-10-08); las
     * demás, Talento Humano.
     */
    public function permisoParaAprobar(): Permiso
    {
        return match ($this) {
            self::ENFERMEDAD, self::ENFERMEDAD_CATASTROFICA, self::CALAMIDAD_DOMESTICA => Permiso::VALIDAR_TRABAJO_SOCIAL,
            default => Permiso::APROBAR_PERMISO_SIRHA7,
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Las de la ley, sin la pactada: las que se ofrecen a todos y las que una
     * cláusula del contrato colectivo puede ampliar.
     *
     * @return list<self>
     */
    public static function legales(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c !== self::PACTADA));
    }
}
