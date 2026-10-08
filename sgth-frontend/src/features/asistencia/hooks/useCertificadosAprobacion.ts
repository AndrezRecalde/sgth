import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  certificadoAprobacionService,
  type FiltrosCertificados,
} from '../services/certificadoAprobacionService'
import type { CertificadoAprobacion } from '@/types/api'

/*
| Los certificados médicos que TH y Trabajo Social aprueban y registran en
| Sirha7 (decisión del 2026-10-08). La previa se pide cada vez que se abre el
| diálogo: los cruces dependen de los permisos de la persona en ese momento.
*/

export function useCertificadosParaAprobar(filtros: FiltrosCertificados, activo = true) {
  return useQuery({
    queryKey: ['certificados-aprobacion', filtros],
    queryFn:  () => certificadoAprobacionService.listar(filtros),
    enabled:  activo,
    staleTime: 0,
  })
}

export function usePreviaCertificado(id: number | null) {
  return useQuery({
    queryKey: ['certificado-previa', id],
    queryFn:  () => certificadoAprobacionService.previa(id ?? 0),
    enabled:  id !== null,
    staleTime: 0,
  })
}

/** Lo que cambia al aprobar: la lista, la previa y los permisos que se cruzan. */
function useAlAprobar() {
  const qc = useQueryClient()

  return () => {
    qc.invalidateQueries({ queryKey: ['certificados-aprobacion'] })
    qc.invalidateQueries({ queryKey: ['certificado-previa'] })
    qc.invalidateQueries({ queryKey: ['permisos'] })
  }
}

export function useAprobarCertificado() {
  const alAprobar = useAlAprobar()

  return useMutation({
    mutationFn: ({ id, leaveId }: { id: number; leaveId: number }) =>
      certificadoAprobacionService.aprobar(id, leaveId),
    onSuccess: (c: CertificadoAprobacion) => {
      const omitidos = c.sirha7_dias_omitidos?.length ?? 0

      notificar.exito(
        'Certificado registrado en Sirha7',
        omitidos
          ? `Quedó como ${c.sirha7_leave_nombre}. Se omitieron ${omitidos} día(s) ya cubiertos o sin jornada.`
          : `Quedó como ${c.sirha7_leave_nombre}.`,
      )
      alAprobar()
    },
    onError: notificar.alFallar('No se pudo registrar el certificado en Sirha7'),
  })
}

export function useAprobarCertificadoSinSirha7() {
  const alAprobar = useAlAprobar()

  return useMutation({
    mutationFn: ({ id, nota }: { id: number; nota: string }) =>
      certificadoAprobacionService.aprobarSinSirha7(id, nota),
    onSuccess: () => {
      notificar.exito('Certificado aprobado', 'Quedó aprobado sin registrarlo en Sirha7.')
      alAprobar()
    },
    onError: notificar.alFallar('No se pudo aprobar el certificado'),
  })
}
