import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { asistenciaService } from '../services/asistenciaService'
import type { AccionMarcacion } from '../utils/marcacionOnline'
import type { Coordenadas } from './useUbicacion'

const CLAVE_HOY = ['marcaciones', 'hoy'] as const

/**
 * El progreso del día y el registro de una marcación en línea.
 *
 * El progreso se consultaba cada 60 segundos por cada pestaña abierta, y cada
 * consulta va al SQL Server del biométrico. Ahora se consulta al abrir, al
 * volver a la pestaña y después de marcar: es cuando puede haber cambiado.
 */
export function useMarcacionOnline(activa: boolean) {
  const qc = useQueryClient()

  const hoy = useQuery({
    queryKey: CLAVE_HOY,
    queryFn: () => asistenciaService.marcaciones.estadoHoy(),
    enabled: activa,
    refetchOnWindowFocus: true,
    staleTime: 30_000,
  })

  const registrar = useMutation({
    mutationFn: ({ accion, ubicacion }: { accion: AccionMarcacion; ubicacion: Coordenadas }) =>
      asistenciaService.marcaciones.registrarOnline({
        checktype: accion.tipo,
        latitud: ubicacion.lat,
        longitud: ubicacion.lon,
      }),
    onSuccess: (_, { accion }) => {
      notificar.exito('Marcación registrada', `«${accion.etiqueta}» quedó guardada en el biométrico.`)
      qc.invalidateQueries({ queryKey: CLAVE_HOY })
    },
    onError: (error, { accion }) =>
      notificar.alFallar(`No se pudo registrar «${accion.etiqueta}»`)(error),
  })

  return { hoy, registrar }
}
