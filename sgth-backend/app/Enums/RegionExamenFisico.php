<?php

namespace App\Enums;

enum RegionExamenFisico: string
{
    case PIEL = 'piel';
    case OJOS = 'ojos';
    case OIDO = 'oido';
    case OROFARINGE = 'orofaringe';
    case NARIZ = 'nariz';
    case CUELLO = 'cuello';
    case TORAX_1 = 'torax_1';
    case TORAX_2 = 'torax_2';
    case ABDOMEN = 'abdomen';
    case COLUMNA = 'columna';
    case PELVIS = 'pelvis';
    case EXTREMIDADES = 'extremidades';
    case NEUROLOGICO = 'neurologico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PIEL => 'Piel',
            self::OJOS => 'Ojos',
            self::OIDO => 'Oído',
            self::OROFARINGE => 'Oro Faringe',
            self::NARIZ => 'Nariz',
            self::CUELLO => 'Cuello',
            // El impreso tiene dos regiones «Tórax» (7 y 8); el numeral las
            // distingue en el PDF y en el asistente.
            self::TORAX_1 => 'Tórax',
            self::TORAX_2 => 'Tórax',
            self::ABDOMEN => 'Abdomen',
            self::COLUMNA => 'Columna',
            self::PELVIS => 'Pelvis',
            self::EXTREMIDADES => 'Extremidades',
            self::NEUROLOGICO => 'Neurológico',
        };
    }

    /** Numeral de la región en la sección F del impreso (1 a 13). */
    public function numero(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * Los ítems de la región, en el orden y con el nombre del impreso
     * SNS-MSP/HCU-form.123/2025 (el PDF les antepone la letra a, b, c…).
     * El asistente del frontend lleva la misma lista en `femoOptions.ts`.
     *
     * @return list<string>
     */
    public function items(): array
    {
        return match ($this) {
            self::PIEL => ['Cicatrices', 'Piel y Faneras'],
            self::OJOS => ['Párpados', 'Conjuntivas', 'Pupilas', 'Córnea', 'Motilidad'],
            self::OIDO => ['Conducto auditivo externo', 'Pabellón', 'Tímpanos'],
            self::OROFARINGE => ['Labios', 'Lengua', 'Faringe', 'Amígdalas', 'Dentadura'],
            self::NARIZ => ['Tabique', 'Cornetes', 'Mucosas', 'Senos paranasales'],
            self::CUELLO => ['Tiroides / Masas', 'Movilidad'],
            self::TORAX_1 => ['Mamas', 'Corazón'],
            self::TORAX_2 => ['Pulmones', 'Corazón', 'Parrilla Costal'],
            self::ABDOMEN => ['Vísceras', 'Pared Abdominal'],
            self::COLUMNA => ['Flexibilidad', 'Desviación', 'Dolor'],
            self::PELVIS => ['Pelvis', 'Genitales'],
            self::EXTREMIDADES => ['Vascular', 'Miembros Superiores', 'Miembros Inferiores'],
            self::NEUROLOGICO => ['Fuerza', 'Sensibilidad', 'Marcha', 'Reflejos'],
        };
    }
}
