import { useAuth } from '@/hooks/useAuth'
import type { Viatico } from '@/types/api'

/**
 * Qué puede hacer el usuario con sesión sobre los viáticos.
 *
 * Es la misma regla que `ViaticoPolicy` en el backend: un botón que acaba en
 * 403 es peor que no mostrarlo. Hasta ahora la pantalla ofrecía aprobar,
 * contabilizar y las autorizaciones de vuelo a cualquiera que la abriera.
 *
 * - El titular edita, cancela y liquida lo suyo.
 * - `aprobar-viatico`: aprueba y rechaza solicitudes y vuelos.
 * - `gestionar-viaticos`: entrega el anticipo, marca la comisión, fija el
 *   monto y corrige cualquier viático.
 * - `liquidar-viatico`: devuelve la liquidación a corrección o la contabiliza.
 * - `ver-viaticos-todos`: consulta (Talento Humano).
 *
 * admin-ti no aparece aquí: el backend le deja todo por `Gate::before`, pero
 * es un rol técnico y no opera viáticos.
 */
export function useAccionesViatico() {
  const { usuario, hasPermiso } = useAuth()

  const aprueba = hasPermiso('aprobar-viatico')
  const gestiona = hasPermiso('gestionar-viaticos')
  const revisaLiquidacion = hasPermiso('liquidar-viatico')

  const esTitular = (v: Pick<Viatico, 'servidor_id'>) =>
    usuario?.servidor_id != null && usuario.servidor_id === v.servidor_id

  const editar = (v: Pick<Viatico, 'servidor_id'>) => gestiona || esTitular(v)

  return {
    veTodos:
      aprueba || gestiona || revisaLiquidacion || hasPermiso('ver-viaticos-todos'),
    solicitar: usuario?.servidor_id != null && hasPermiso('solicitar-viatico'),
    aprobar: aprueba,
    rechazar: aprueba,
    autorizarVuelos: aprueba,
    operar: gestiona,
    revisarLiquidacion: revisaLiquidacion,
    editar,
    cancelar: editar,
  }
}

export type AccionesViatico = ReturnType<typeof useAccionesViatico>
