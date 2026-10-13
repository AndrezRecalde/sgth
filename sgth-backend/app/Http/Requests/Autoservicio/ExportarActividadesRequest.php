<?php

namespace App\Http\Requests\Autoservicio;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Los mismos filtros, para el PDF: aquí el rango es obligatorio, porque es el
 * período que el informe dice cubrir.
 */
class ExportarActividadesRequest extends FiltrarActividadesRequest
{
    /** Un año: un informe más largo no lo firma nadie, y dompdf no lo aguanta. */
    public const MAX_DIAS = 366;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $dias = Carbon::parse($this->input('desde'))->diffInDays(Carbon::parse($this->input('hasta')));
                if ($dias >= self::MAX_DIAS) {
                    $validator->errors()->add('hasta', 'El informe cubre hasta un año: acorte el rango.');
                }
            },
        ];
    }
}
