import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import type { ServidorConRelaciones, UnidadConRelaciones } from '@/types/api'

export const nombreServidor = (s: ServidorConRelaciones): string =>
  [s.apellido, s.nombre].filter(Boolean).join(' ')

/** «Apellido Nombre — cédula»: así se elige a un servidor en un selector. */
export const opcionServidor = (s: ServidorConRelaciones) => ({
  value: String(s.id),
  label: `${nombreServidor(s)} — ${s.cedula}`,
})

/**
 * Las unidades y los servidores de la unidad elegida, para los selectores de
 * quién solicita, quién firma y quién reemplaza en permisos y vacaciones.
 *
 * Los servidores se piden ya filtrados por unidad. Traer los primeros 200 y
 * filtrar en el navegador dejaba vacías las unidades que caían fuera de esa
 * primera página: con más de 200 servidores en la institución, el selector
 * decía que no había nadie. Es el mismo patrón que usan MovimientoModal y
 * SubrogacionModal.
 */
export function useOpcionesSolicitante(unidadId: number | null) {
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: servidoresData } = useServidores(
    unidadId ? { unidad_administrativa_id: unidadId, per_page: 100 } : undefined,
  )
  const servidores = unidadId
    ? ((servidoresData?.data ?? []) as ServidorConRelaciones[])
    : []

  return {
    servidores,
    opcionesUnidad: unidades.map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
    // El jefe se elige entre los de la misma unidad con puesto de jefatura.
    opcionesJefe: servidores
      .filter(s => (s.puesto as { es_jefe?: boolean } | null)?.es_jefe === true)
      .map(s => ({ value: String(s.id), label: nombreServidor(s) })),
  }
}
