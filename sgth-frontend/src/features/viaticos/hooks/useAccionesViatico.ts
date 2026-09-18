import { useAuth } from '@/hooks/useAuth'
import type { Viatico, ViaticoConRelaciones } from '@/types/api'

/**
 * Qué puede hacer el usuario con sesión sobre los viáticos.
 *
 * Es la misma regla que el backend, en dos partes: quién puede
 * (`ViaticoPolicy`) y cuándo (`ViaticoEstadoService`). Un botón que acaba en
 * 403 o en 422 es peor que no mostrarlo.
 *
 * - El titular edita, cancela y liquida lo suyo; corrige solo en `solicitado`.
 * - `aprobar-viatico`: aprueba y rechaza solicitudes (antes del anticipo) y
 *   vuelos.
 * - `gestionar-viaticos`: entrega el anticipo, marca la comisión y corrige
 *   cualquier viático hasta que se liquida.
 * - `liquidar-viatico`: devuelve la liquidación a corrección o la contabiliza.
 * - `ver-viaticos-todos`: consulta (Talento Humano).
 * - Nadie aprueba, rechaza, entrega el anticipo, contabiliza ni autoriza los vuelos de
 *   un viático en el que viaja. Como Financiero tampoco lo corrige: para él
 *   rige lo mismo que para el titular.
 *
 * admin-ti no aparece aquí: el backend le deja todo por `Gate::before`, pero
 * es un rol técnico y no opera viáticos.
 */

type ViaticoAcciones = Pick<Viatico, 'servidor_id'> &
  Pick<ViaticoConRelaciones, 'estado' | 'modalidad_anticipo'>

const EDITABLES_POR_QUIEN_OPERA = [
  'solicitado', 'aprobado', 'con_anticipo', 'en_comision', 'pendiente_liquidacion',
]

export function useAccionesViatico() {
  const { usuario, hasPermiso } = useAuth()
  const miServidor = usuario?.servidor_id ?? null

  const aprueba = hasPermiso('aprobar-viatico')
  const gestiona = hasPermiso('gestionar-viaticos')
  const revisaLiquidacion = hasPermiso('liquidar-viatico')

  const esTitular = (v: Pick<Viatico, 'servidor_id'>) =>
    miServidor !== null && miServidor === v.servidor_id

  /** Un viático es de un solo servidor: su titular. */
  const viajaEn = (v: ViaticoAcciones) => esTitular(v)

  const estado = (v: ViaticoAcciones) => String(v.estado ?? '')

  const editar = (v: ViaticoAcciones) =>
    gestiona && !viajaEn(v)
      ? EDITABLES_POR_QUIEN_OPERA.includes(estado(v))
      : (esTitular(v) || gestiona) && estado(v) === 'solicitado'

  return {
    veTodos:
      aprueba || gestiona || revisaLiquidacion || hasPermiso('ver-viaticos-todos'),
    solicitar: miServidor !== null && hasPermiso('solicitar-viatico'),
    esTitular,
    editar,
    /** Presentar la liquidación: el titular o quien opera, con ella abierta. */
    liquidar: (v: ViaticoAcciones) =>
      (esTitular(v) || gestiona) && estado(v) === 'pendiente_liquidacion',
    cancelar: (v: ViaticoAcciones) =>
      (esTitular(v) || gestiona) && estado(v) === 'solicitado',
    aprobar: (v: ViaticoAcciones) =>
      aprueba && !viajaEn(v) && estado(v) === 'solicitado',
    rechazar: (v: ViaticoAcciones) =>
      aprueba && !viajaEn(v) && ['solicitado', 'aprobado'].includes(estado(v)),
    /** Tiene el permiso de aprobar pero viaja en este: le toca a otra persona. */
    apruebaPeroViaja: (v: ViaticoAcciones) =>
      aprueba && viajaEn(v) && estado(v) === 'solicitado',
    entregarAnticipo: (v: ViaticoAcciones) =>
      gestiona && !viajaEn(v) && estado(v) === 'aprobado' &&
      String(v.modalidad_anticipo) !== 'sin_anticipo',
    marcarEnComision: (v: ViaticoAcciones) =>
      gestiona && ['aprobado', 'con_anticipo'].includes(estado(v)),
    marcarPendiente: (v: ViaticoAcciones) =>
      gestiona && estado(v) === 'en_comision',
    revisarLiquidacion: (v: ViaticoAcciones) =>
      revisaLiquidacion && estado(v) === 'liquidado',
    contabilizar: (v: ViaticoAcciones) =>
      revisaLiquidacion && !viajaEn(v) && estado(v) === 'liquidado',
    autorizarVuelos: aprueba,
    /** Decidir un vuelo: nunca el de un viático en el que se viaja. */
    decidirVuelo: (servidorId: number | null) =>
      aprueba && (miServidor === null || servidorId !== miServidor),
  }
}

export type AccionesViatico = ReturnType<typeof useAccionesViatico>
