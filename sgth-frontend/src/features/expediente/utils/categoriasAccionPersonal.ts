// Refleja los tipos de TipoMovimientoPersonal (backend) que pasan por la
// máquina de estados de MovimientoPersonalStateService — es decir,
// creaVinculo() + modificaVinculo() + esAccionDePersonal(). requiereVinculo
// distingue el único tipo que crea el primer vínculo ('ingreso') del resto,
// que necesitan un ContratoServidor vigente ya existente.
export interface CategoriaAccionPersonal {
  value: string
  label: string
  requiereVinculo: boolean
}

export const CATEGORIAS_ACCION_PERSONAL: CategoriaAccionPersonal[] = [
  { value: 'ingreso', label: 'Ingreso y Vinculación', requiereVinculo: false },
  { value: 'traslado', label: 'Traslado', requiereVinculo: true },
  { value: 'ascenso', label: 'Ascenso', requiereVinculo: true },
  { value: 'traspaso', label: 'Traspaso', requiereVinculo: true },
  { value: 'cambio_administrativo', label: 'Cambio Administrativo', requiereVinculo: true },
  { value: 'cambio_denominacion', label: 'Cambio de Denominación', requiereVinculo: true },
  { value: 'prestacion_servicios', label: 'Prestación de Servicios', requiereVinculo: true },
  { value: 'comision_sin_remuneracion', label: 'Comisión de Servicios sin Remuneración', requiereVinculo: true },
  { value: 'licencia_sin_remuneracion', label: 'Licencia sin Remuneración', requiereVinculo: true },
  { value: 'incremento_remuneracion', label: 'Incremento de Remuneración', requiereVinculo: true },
]

/**
 * true si la categoría está habilitada dado el estado de vínculo del
 * servidor seleccionado. pendiente_vinculacion null (desconocido) deshabilita
 * todo por defecto.
 */
export function categoriaHabilitada(
  categoria: CategoriaAccionPersonal,
  pendienteVinculacion: boolean | null | undefined
): boolean {
  if (pendienteVinculacion === true) return !categoria.requiereVinculo
  if (pendienteVinculacion === false) return categoria.requiereVinculo
  return false
}
