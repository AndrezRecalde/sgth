import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

export interface ParamsMarcaciones {
  cedula:       string
  fecha_inicio: string
  fecha_fin:    string
}

/**
 * Las marcaciones de una cédula en un rango, del biométrico.
 *
 * Recibe los filtros YA CONSULTADOS: `null` mientras nadie haya pulsado
 * «Consultar». Antes una bandera se encendía con el primer clic y ya no se
 * apagaba, así que después cada cambio de servidor o de fecha lanzaba sola
 * una consulta al SQL Server del biométrico. Es el mismo defecto que se
 * corrigió en el consolidado de permisos (`useConsolidadoPermisos`).
 */
export function useMarcaciones(params: ParamsMarcaciones | null) {
  return useQuery({
    queryKey:  ['marcaciones', params],
    queryFn:   () => asistenciaService.marcaciones.listar(params!),
    enabled:   !!params,
    staleTime: 0,
  })
}
