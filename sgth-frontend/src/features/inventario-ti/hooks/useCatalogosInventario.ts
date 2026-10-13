import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesInventario } from '../constants/claves'
import { catalogosService } from '../services/catalogosService'

const DIEZ_MINUTOS = 1000 * 60 * 10

/** Todo lo que el formulario y los filtros de un equipo necesitan. */
export function useCatalogosFormulario() {
  return useQuery({
    queryKey: clavesInventario.catalogos.formularios,
    queryFn: catalogosService.formularios,
    staleTime: DIEZ_MINUTOS,
  })
}

export const useTiposBien = () =>
  useQuery({ queryKey: clavesInventario.catalogos.tipos, queryFn: catalogosService.tipos })

export const useMarcas = () =>
  useQuery({ queryKey: clavesInventario.catalogos.marcas, queryFn: catalogosService.marcas })

export const useUbicaciones = () =>
  useQuery({ queryKey: clavesInventario.catalogos.ubicaciones, queryFn: catalogosService.ubicaciones })

export const useContratosVigencia = () =>
  useQuery({ queryKey: clavesInventario.catalogos.contratos, queryFn: catalogosService.contratos })

type Recurso = 'tipo' | 'subtipo' | 'marca' | 'ubicacion' | 'contrato'

/** El trío de llamadas de un catálogo (`catalogosService.marca`, …). */
type ServicioCatalogo<D> = {
  crear: (data: D) => Promise<unknown>
  actualizar: (id: number, data: D) => Promise<unknown>
  eliminar: (id: number) => Promise<unknown>
}

const NOMBRES: Record<Recurso, { el: string; guardado: string; eliminado: string }> = {
  tipo:      { el: 'el tipo',      guardado: 'Tipo guardado',      eliminado: 'Tipo eliminado' },
  subtipo:   { el: 'el subtipo',   guardado: 'Subtipo guardado',   eliminado: 'Subtipo eliminado' },
  marca:     { el: 'la marca',     guardado: 'Marca guardada',     eliminado: 'Marca eliminada' },
  ubicacion: { el: 'la ubicación', guardado: 'Ubicación guardada', eliminado: 'Ubicación eliminada' },
  contrato:  { el: 'el contrato',  guardado: 'Contrato guardado',  eliminado: 'Contrato eliminado' },
}

/**
 * Guardar y eliminar un registro de catálogo:
 * `useCatalogoMutations('marca', catalogosService.marca)`. Cualquier cambio
 * invalida todo el inventario.
 */
export function useCatalogoMutations<D>(recurso: Recurso, servicio: ServicioCatalogo<D>) {
  const qc = useQueryClient()
  const nombre = NOMBRES[recurso]
  // La raíz y no solo los catálogos: renombrar una marca cambia lo que muestran
  // el listado y las fichas.
  const invalidar = () => qc.invalidateQueries({ queryKey: clavesInventario.raiz })

  const guardar = useMutation({
    mutationFn: ({ id, data }: { id?: number; data: D }) => (id ? servicio.actualizar(id, data) : servicio.crear(data)),
    onSuccess: () => {
      notificar.exito(nombre.guardado, 'Ya está disponible en los formularios del inventario.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos(`No se pudo guardar ${nombre.el}`),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => servicio.eliminar(id),
    onSuccess: () => {
      notificar.exito(nombre.eliminado, 'Ya no aparece en los formularios del inventario.')
      invalidar()
    },
    onError: notificar.alFallar(`No se pudo eliminar ${nombre.el}`),
  })

  return { guardar, eliminar }
}
