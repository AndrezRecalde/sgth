<?php

namespace App\Services\Nomina;

use App\Exceptions\ReglaNegocioException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Un mes del rol de pagos, de treinta días (fase 7.3a): es el mes que ya usa
 * el descuento de la suspensión (remuneración ÷ 30 × días) y el de la
 * liquidación del obrero.
 *
 * Cada fecha cuenta como su día del mes, salvo el 31, que no existe, y el
 * último día de febrero, que vale 30. Quien entra el 16 cobra 15 días; quien
 * sale el 28 de febrero, el mes entero; una licencia del 1 al 31, 30 días.
 */
final class MesDeNomina
{
    public const DIAS = 30;

    public readonly CarbonImmutable $inicio;

    public readonly CarbonImmutable $fin;

    private function __construct(public readonly string $periodo)
    {
        $this->inicio = CarbonImmutable::createFromFormat('!Y-m', $periodo)->startOfMonth();
        $this->fin = $this->inicio->endOfMonth()->startOfDay();
    }

    public static function de(string $periodo): self
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            throw new ReglaNegocioException('El mes se indica como AAAA-MM.');
        }

        return new self($periodo);
    }

    public static function actual(): self
    {
        return new self(now()->format('Y-m'));
    }

    /** «octubre de 2026». */
    public function etiqueta(): string
    {
        return $this->inicio->locale('es')->translatedFormat('F \d\e Y');
    }

    public function contiene(?CarbonInterface $fecha): bool
    {
        return $fecha !== null
            && $fecha->toDateString() >= $this->inicio->toDateString()
            && $fecha->toDateString() <= $this->fin->toDateString();
    }

    /**
     * Lo que el período toca del mes: null si no lo toca. Sin `hasta`, el
     * período sigue abierto y llega al fin del mes.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function recorte(CarbonInterface $desde, ?CarbonInterface $hasta): ?array
    {
        $d = CarbonImmutable::parse($desde->toDateString());
        $h = $hasta ? CarbonImmutable::parse($hasta->toDateString()) : $this->fin;

        $d = $d->lt($this->inicio) ? $this->inicio : $d;
        $h = $h->gt($this->fin) ? $this->fin : $h;

        return $d->gt($h) ? null : [$d, $h];
    }

    /** Los días del período dentro del mes, en el mes de treinta. */
    public function dias(CarbonInterface $desde, ?CarbonInterface $hasta): int
    {
        $recorte = $this->recorte($desde, $hasta);

        if (! $recorte) {
            return 0;
        }

        [$d, $h] = $recorte;

        return max(0, $this->diaComercial($h) - $this->diaComercial($d) + 1);
    }

    private function diaComercial(CarbonImmutable $fecha): int
    {
        return $fecha->isSameDay($this->fin) ? self::DIAS : min($fecha->day, self::DIAS);
    }
}
