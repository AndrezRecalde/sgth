<?php

namespace App\Http\Requests\Autoservicio;

use App\Enums\TipoParentesco;
use App\Enums\TipoSolicitudCarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que el servidor manda desde «Mi perfil» sobre sus cargas familiares
 * (docs/diseno-cargas-familiares-portal.md, 3.2 y 5.2): el registro trae al
 * familiar entero, la corrección sus datos nuevos y la baja su motivo. Lo que
 * depende de la ficha —la cédula que ya consta, el cónyuge activo, el
 * documento de una corrección— lo mira SolicitudCargaFamiliarService.
 */
class PresentarSolicitudCargaRequest extends FormRequest
{
    /** El mismo tope que los títulos de «Mi perfil». */
    private const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $alta = $this->input('tipo') === TipoSolicitudCarga::ALTA->value;

        return [
            'tipo'              => ['required', Rule::enum(TipoSolicitudCarga::class)],
            'carga_familiar_id' => ['exclude_if:tipo,alta', 'required', 'integer'],
            // La cédula no se corrige: solo viene en un registro.
            'cedula'            => ['exclude_unless:tipo,alta', 'required', 'digits:10'],
            'nombres'           => ['exclude_if:tipo,baja', 'required', 'string', 'max:100'],
            'apellidos'         => ['exclude_if:tipo,baja', 'required', 'string', 'max:100'],
            'parentesco'        => ['exclude_if:tipo,baja', 'required', Rule::enum(TipoParentesco::class)],
            // Desde el mismo día en que nace (D8).
            'fecha_nacimiento'  => ['exclude_if:tipo,baja', 'required', 'date', 'before_or_equal:today'],
            'genero'            => ['exclude_if:tipo,baja', 'required', 'in:masculino,femenino'],
            'motivo'            => ['exclude_if:tipo,alta', 'required_if:tipo,baja', 'nullable', 'string', 'max:1000'],
            // Obligatorio para un hijo; opcional para el cónyuge o conviviente (D11).
            'documento'         => [
                Rule::requiredIf($alta && $this->input('parentesco') === TipoParentesco::HIJO->value),
                'nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.required'              => 'Adjunte la partida de nacimiento o la cédula de su hijo en PDF: con ella lo valida Talento Humano.',
            'documento.mimes'                 => 'El documento tiene que ser un PDF.',
            'documento.max'                   => 'El PDF no puede pasar de 10 MB.',
            'cedula.digits'                   => 'La cédula debe tener 10 dígitos.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser posterior a hoy.',
            'motivo.required_if'              => 'Indique por qué este familiar deja de ser su carga.',
            'genero.in'                       => 'El sexo debe ser masculino o femenino.',
        ];
    }

    public function attributes(): array
    {
        return [
            'carga_familiar_id' => 'familiar',
            'genero'            => 'sexo',
            'motivo'            => 'motivo',
        ];
    }
}
