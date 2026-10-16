<?php

namespace App\Http\Resources\Comunicacion;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Services\Comunicacion\DatosCredencial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * «Mi credencial» en el Portal (docs/diseno-comunicacion-social.md, 6.1): la
 * credencial digital del propio servidor, siempre con los datos actuales del
 * expediente. Lleva el tipo de sangre y el contacto de emergencia (D6): son
 * suyos. La foto se pide aparte, por su id, como en el resto del sistema.
 *
 * @mixin \App\Models\Comunicacion\Credencial
 */
class MiCredencialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = DatosCredencial::de($this->servidor, $this->resource);

        return [
            'id'               => $this->id,
            'estado'           => $this->estado->value,
            'estado_etiqueta'  => $this->estado->etiqueta(),
            'vigente'          => $this->estaVigente(),
            'valida_hasta'     => $this->valida_hasta?->toDateString(),
            'datos'            => $datos,
            // Lo que no sale en la credencial porque falta en el expediente.
            'datos_faltantes'  => DatosCredencial::faltantes($datos),
            // El QR en data: URL, para no sumar una librería al frontend.
            'qr'               => app(CredencialServiceInterface::class)->qr($this->resource),
            'url_verificacion' => $this->urlVerificacion(),
        ];
    }
}
