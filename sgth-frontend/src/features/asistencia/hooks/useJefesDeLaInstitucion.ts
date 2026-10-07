import { useServidores } from '@/features/expediente/hooks/useServidores'
import { nombreServidor } from './useOpcionesSolicitante'
import type { ServidorConRelaciones } from '@/types/api'

/**
 * Los jefes en funciones de toda la institución, para que Talento Humano elija
 * el firmante de un permiso fuera de la unidad del servidor.
 *
 * El jefe inmediato de un jefe de unidad está en otra unidad, y el jefe de
 * Talento Humano no tiene a nadie más en la suya: con solo los jefes de la
 * unidad, su permiso no tenía firmante posible. Son pocos —uno por unidad—,
 * así que se piden de una vez y se reusan unos minutos.
 */
export function useJefesDeLaInstitucion() {
  const { data } = useServidores({ es_jefe: true, en_funciones: true, per_page: 500 })
  const jefes = (data?.data ?? []) as ServidorConRelaciones[]

  return jefes.map((s) => ({
    value: String(s.id),
    label: s.unidad_administrativa?.nombre
      ? `${nombreServidor(s)} — ${s.unidad_administrativa.nombre}`
      : nombreServidor(s),
  }))
}
