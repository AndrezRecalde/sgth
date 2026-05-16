<?php

namespace App\Http\Requests\Viatico;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransporteViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:bus_interprovincial,avion,vehiculo_propio,taxi,transporte_institucional,otro'],
            'provincia_origen_id' => ['nullable', 'exists:provincias,id'],
            'canton_origen_id' => ['nullable', 'exists:cantones,id'],
            'provincia_destino_id' => ['nullable', 'exists:provincias,id'],
            'canton_destino_id' => ['nullable', 'exists:cantones,id'],
            'pais_origen' => ['nullable', 'string', 'max:100'],
            'pais_destino' => ['nullable', 'string', 'max:100'],
            'fecha_viaje' => ['required', 'date'],
            
            'empresa_o_aerolinea' => ['required_if:tipo,avion', 'nullable', 'string', 'max:100'],
            'numero_ticket_o_billete' => ['required_if:tipo,avion', 'nullable', 'string', 'max:100'],
            
            'placa_vehiculo' => ['required_if:tipo,vehiculo_propio', 'nullable', 'string', 'max:20'],
            'kilometraje' => ['required_if:tipo,vehiculo_propio', 'nullable', 'numeric', 'min:0.1'],
            'valor_kilometro' => ['required_if:tipo,vehiculo_propio', 'nullable', 'numeric', 'min:0'],
            
            'monto' => ['required', 'numeric', 'min:0'],
            'archivo_respaldo' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de transporte es obligatorio.',
            'tipo.in' => 'El tipo de transporte no es válido.',
            'fecha_viaje.required' => 'La fecha de viaje es obligatoria.',
            
            'empresa_o_aerolinea.required_if' => 'La empresa o aerolínea es obligatoria para vuelos.',
            'numero_ticket_o_billete.required_if' => 'El número de ticket es obligatorio para vuelos.',
            
            'placa_vehiculo.required_if' => 'La placa del vehículo es obligatoria para vehículo propio.',
            'kilometraje.required_if' => 'El kilometraje es obligatorio para vehículo propio.',
            'valor_kilometro.required_if' => 'El valor por kilómetro es obligatorio para vehículo propio.',
            
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser numérico.',
        ];
    }
}
