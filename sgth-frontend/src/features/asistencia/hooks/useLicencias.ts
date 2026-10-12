import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { licenciaService, type FiltrosLicencias, type Licencia, type RegistrarLicenciaData } from '../services/licenciaService'

/*
| Las licencias con remuneración, fuera de las vacaciones (fase 5.1). La previa
| se pide cada vez que se abre el diálogo de aprobación.
*/

export function useLicencias(filtros: FiltrosLicencias) {
  return useQuery({
    queryKey: ['licencias', filtros],
    queryFn: () => licenciaService.listar(filtros),
    staleTime: 0,
  })
}

export function useCausasLicencia() {
  return useQuery({
    queryKey: ['licencias-causas'],
    queryFn: licenciaService.causas,
    staleTime: 1000 * 60 * 30,
  })
}

export function usePreviaLicencia(id: number | null) {
  return useQuery({
    queryKey: ['licencia-previa', id],
    queryFn: () => licenciaService.previa(id ?? 0),
    enabled: id !== null,
    staleTime: 0,
  })
}

/** Una licencia ocupa días: cambia lo que ven las vacaciones y los permisos. */
function useAlCambiar() {
  const qc = useQueryClient()

  return () => {
    qc.invalidateQueries({ queryKey: ['licencias'] })
    qc.invalidateQueries({ queryKey: ['licencia-previa'] })
    qc.invalidateQueries({ queryKey: ['vacaciones'] })
    qc.invalidateQueries({ queryKey: ['permisos'] })
  }
}

export function useRegistrarLicencia() {
  const alCambiar = useAlCambiar()

  return useMutation({
    mutationFn: (data: RegistrarLicenciaData) => licenciaService.registrar(data),
    onSuccess: (l: Licencia) => {
      notificar.exito('Licencia registrada', `${l.folio}: queda pendiente de aprobación.`)
      alCambiar()
    },
    // Los errores de campo los pinta el formulario; el tope o un cruce llegan
    // como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la licencia'),
  })
}

export function useAprobarLicencia() {
  const alCambiar = useAlCambiar()

  return useMutation({
    mutationFn: ({ id, leaveId }: { id: number; leaveId: number }) => licenciaService.aprobar(id, leaveId),
    onSuccess: (l: Licencia) => {
      const omitidos = l.sirha7_dias_omitidos?.length ?? 0

      notificar.exito(
        'Licencia registrada en Sirha7',
        omitidos
          ? `Quedó como ${l.sirha7_leave_nombre}. Se omitieron ${omitidos} día(s) ya cubiertos o sin jornada.`
          : `Quedó como ${l.sirha7_leave_nombre}.`,
      )
      alCambiar()
    },
    onError: notificar.alFallar('No se pudo registrar la licencia en Sirha7'),
  })
}

export function useAprobarLicenciaSinSirha7() {
  const alCambiar = useAlCambiar()

  return useMutation({
    mutationFn: ({ id, nota }: { id: number; nota: string }) => licenciaService.aprobarSinSirha7(id, nota),
    onSuccess: () => {
      notificar.exito('Licencia aprobada', 'Quedó aprobada sin registrarla en Sirha7.')
      alCambiar()
    },
    onError: notificar.alFallar('No se pudo aprobar la licencia'),
  })
}

export function useAnularLicencia() {
  const alCambiar = useAlCambiar()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => licenciaService.anular(id, motivo),
    onSuccess: (l: Licencia) => {
      notificar.exito('Licencia anulada', `${l.folio}: sus días quedan libres.`)
      alCambiar()
    },
    onError: notificar.alFallar('No se pudo anular la licencia'),
  })
}
