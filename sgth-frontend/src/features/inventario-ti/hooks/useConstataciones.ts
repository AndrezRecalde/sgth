import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesInventario } from '../constants/claves'
import { constatacionesService, type NuevaConstatacionData } from '../services/constatacionesService'

export const useConstataciones = () =>
  useQuery({ queryKey: clavesInventario.constataciones.todas, queryFn: constatacionesService.listar })

export const useConstatacion = (id: number) =>
  useQuery({ queryKey: clavesInventario.constataciones.una(id), queryFn: () => constatacionesService.obtener(id), enabled: id > 0 })

/**
 * Los pasos de una constatación. Escanear no notifica: el resultado se ve en
 * la tarjeta del último escaneo, que es lo que se mira con el celular en la
 * mano. Mover y cerrar cambian las fichas: se invalida todo el inventario.
 */
export function useConstatacionMutations(id = 0) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesInventario.constataciones.todas })

  const crear = useMutation({
    mutationFn: (data: NuevaConstatacionData) => constatacionesService.crear(data),
    onSuccess: () => refrescar(),
    onError: notificar.alFallarSalvoCampos('No se pudo abrir la constatación'),
  })
  const escanear = useMutation({
    mutationFn: (codigo: string) => constatacionesService.escanear(id, codigo),
    onSuccess: () => qc.invalidateQueries({ queryKey: clavesInventario.constataciones.una(id) }),
    onError: notificar.alFallar('No se pudo registrar el escaneo'),
  })
  const mover = useMutation({
    mutationFn: (itemId: number) => constatacionesService.mover(id, itemId),
    onSuccess: () => {
      notificar.exito('Equipo movido', 'Quedó en esta ubicación, con sus componentes.')
      qc.invalidateQueries({ queryKey: clavesInventario.raiz })
    },
    onError: notificar.alFallar('No se pudo mover el equipo'),
  })
  const cerrar = useMutation({
    mutationFn: (observaciones: string | null) => constatacionesService.cerrar(id, observaciones),
    onSuccess: () => {
      notificar.exito('Constatación cerrada', 'Lo que no apareció quedó como no encontrado.')
      qc.invalidateQueries({ queryKey: clavesInventario.raiz })
    },
    onError: notificar.alFallar('No se pudo cerrar la constatación'),
  })
  const acta = useMutation({
    mutationFn: async () => abrirOGuardarArchivo(await constatacionesService.acta(id), `constatacion-${id}.pdf`),
    onError: notificar.alFallar('No se pudo generar el acta'),
  })

  return { crear, escanear, mover, cerrar, acta }
}
