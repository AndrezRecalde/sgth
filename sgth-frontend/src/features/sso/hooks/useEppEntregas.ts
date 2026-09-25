import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import type { EppEntrega } from '../services/ssoService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  servidor_id?: number
  equipo_proteccion_id?: number
  fecha_inicio?: string
  fecha_fin?: string
}

export function useEppEntregas(params?: Params) {
  return useQuery({
    queryKey: clavesSso.epp.entregas.lista(params),
    queryFn: () => ssoService.listarEntregasEpp(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useReporteEppEntregas(params: { fecha_inicio: string; fecha_fin: string; puesto_id?: number } | null) {
  return useQuery({
    queryKey: clavesSso.epp.entregas.reporte(params),
    queryFn: () => ssoService.reporteEntregasEpp(params!),
    enabled: !!params,
    staleTime: 1000 * 60,
  })
}

export function useKitEppServidor(servidorId: number | null) {
  return useQuery({
    queryKey: clavesSso.epp.kit(servidorId),
    queryFn: () => ssoService.obtenerKitEppServidor(servidorId!),
    enabled: !!servidorId,
    staleTime: 1000 * 30,
  })
}

export function useEppEntregaMutations() {
  const qc = useQueryClient()

  // Una entrega cambia cuatro cosas, y antes solo se invalidaba una: el
  // listado. El reporte por rango y el kit del servidor tenían claves
  // hermanas, no hijas, así que no los alcanzaba: se entregaba el kit
  // completo y el modal seguía mostrando los mismos equipos pendientes, con
  // la misma fecha de última entrega. La cobertura de EPP de los indicadores
  // proactivos y el tablero se calculan también sobre estas entregas.
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.epp.entregas.todas })
    qc.invalidateQueries({ queryKey: clavesSso.epp.kits })
    qc.invalidateQueries({ queryKey: clavesSso.indicadores.todos })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const registrar = useMutation({
    mutationFn: (data: Partial<EppEntrega>) => ssoService.registrarEntregaEpp(data),
    onSuccess: () => {
      notificar.exito(
        'Entrega registrada',
        'El movimiento de EPP fue registrado correctamente.',
      )
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la entrega'),
  })

  const registrarKit = useMutation({
    mutationFn: (data: {
      servidor_id: number
      fecha_entrega: string
      observaciones?: string
      equipos: { equipo_proteccion_id: number; cantidad?: number }[]
    }) => ssoService.registrarEntregaKitEpp(data),
    onSuccess: (entregas) => {
      notificar.exito(
        'Kit entregado',
        `Se registraron ${entregas.length} equipos de protección.`,
      )
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo entregar el kit'),
  })

  return { registrar, registrarKit }
}
