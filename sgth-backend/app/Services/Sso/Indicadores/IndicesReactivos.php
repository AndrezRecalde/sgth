<?php

namespace App\Services\Sso\Indicadores;

/**
 * Índices reactivos del CD 513 (Resolución IESS, Reglamento del Seguro General
 * de Riesgos del Trabajo):
 *
 *   IF = (nº de lesiones × 200 000) / horas trabajadas
 *   IG = (días perdidos × 200 000) / horas trabajadas
 *   TR = IG / IF
 *
 * Vive aparte del servicio, junto a HorasTrabajadas, por lo mismo: es la parte
 * con consecuencia legal —lo que se informa al IESS— y así se prueba sin base
 * de datos. El servicio se queda con lo que sí necesita la base: contar las
 * lesiones del período y resolver el denominador.
 *
 * NOTA heredada del cálculo anterior, que sigue en pie: estas fórmulas se
 * verificaron solo contra fuentes secundarias —el reglamento oficial del IESS
 * no se pudo confirmar contra un PDF primario legible—, así que Talento Humano
 * debe confirmarlas antes de tratarlas como referencia legal definitiva.
 */
final class IndicesReactivos
{
    /** Las horas-hombre de referencia del CD 513. */
    public const HORAS_REFERENCIA = 200000;

    private function __construct(
        public readonly float $indiceFrecuencia,
        public readonly float $indiceGravedad,
        public readonly float $tasaRiesgo,
    ) {}

    /**
     * @param int $numeroLesiones accidentes con lesión del período
     * @param int $diasPerdidos   días de reposo médico acumulados
     * @param int $horasTrabajadas denominador; debe ser mayor que cero
     */
    public static function desde(int $numeroLesiones, int $diasPerdidos, int $horasTrabajadas): self
    {
        if ($horasTrabajadas <= 0) {
            // Sin denominador no hay índice. Quien llama ya distingue ese caso
            // ('sin_datos') porque tiene que explicar qué falta cargar.
            throw new \InvalidArgumentException('Las horas trabajadas deben ser mayores que cero.');
        }

        $indiceFrecuencia = round(($numeroLesiones * self::HORAS_REFERENCIA) / $horasTrabajadas, 2);
        $indiceGravedad = round(($diasPerdidos * self::HORAS_REFERENCIA) / $horasTrabajadas, 2);

        // TR sale de los dos índices ya redondeados, como se venía informando.
        // Equivale a días perdidos / lesiones, y arrastra el redondeo de ambos:
        // la diferencia aparece en el tercer decimal y no en el segundo, que es
        // el que se reporta. Cambiarlo movería cifras ya entregadas, así que se
        // deja como está y queda anotado.
        $tasaRiesgo = $indiceFrecuencia > 0
            ? round($indiceGravedad / $indiceFrecuencia, 2)
            : 0.0;

        return new self($indiceFrecuencia, $indiceGravedad, $tasaRiesgo);
    }
}
