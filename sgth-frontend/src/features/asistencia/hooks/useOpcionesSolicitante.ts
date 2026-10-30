import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useServidoresDeUnidad } from '@/features/expediente/hooks/useServidores'
import type { ServidorBusqueda } from '@/types/api'

export const nombreServidor = (s: ServidorBusqueda): string =>
  [s.apellido, s.nombre].filter(Boolean).join(' ')

/** «Apellido Nombre — cédula»: así se elige a un servidor en un selector. */
export const opcionServidor = (s: ServidorBusqueda) => ({
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
 *
 * Salen del buscador y de la lista pública de unidades, y no del Expediente ni
 * de Estructura, que piden `ver-expediente-todos` y `ver-estructura`: el técnico
 * de Asistencia registra a nombre de otro sin ver expedientes.
 */
export function useOpcionesSolicitante(unidadId: number | null) {
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })

  const { data: servidores = [], isLoading } = useServidoresDeUnidad(unidadId)

  return {
    servidores,
    /** Pidiendo los servidores de la unidad elegida: «sin servidores» todavía no es cierto. */
    cargandoServidores: unidadId !== null && isLoading,
    opcionesUnidad: unidades.map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
    // El jefe se elige entre los de la misma unidad con puesto de jefatura.
    opcionesJefe: servidores
      .filter(s => s.es_jefe)
      .map(s => ({ value: String(s.id), label: nombreServidor(s) })),
  }
}
