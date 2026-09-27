'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconBan, IconFileDownload } from '@tabler/icons-react'
import { confirmar, ModalFooter, notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { movimientoService } from '../services/movimientoService'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import {
  ESTADO_LABELS, TRANSICIONES, puedeDescargarPdf, requiereCompletarVinculo,
} from '../utils/estadoAccionPersonal'
import { tieneEfectoEconomico } from '../utils/taxonomiaAccionPersonal'
import type { MovimientoPersonal } from '@/types/api'
import { guardarArchivo } from '@/lib/archivo'

interface Props {
  m: MovimientoPersonal
  onClose: () => void
  /** Un ingreso que pasa a registrada necesita los datos del contrato. */
  onCompletarVinculo: () => void
  /** Suscribir algo con efecto económico necesita el dictamen presupuestario. */
  onPedirDictamen: () => void
}

/**
 * Lo que hace avanzar el trámite, y nada más: editar vive junto a los datos que
 * corrige.
 *
 * A la izquierda lo secundario —descargar el documento— y lo destructivo; a la
 * derecha el único paso hacia adelante que el grafo permite desde este estado,
 * porque es uno o ninguno: borrador→suscrita, suscrita→registrada,
 * registrada→notificada.
 */
export function AccionPersonalPie({ m, onClose, onCompletarVinculo, onPedirDictamen }: Props) {
  const { transicionar } = useMovimientoMutations()
  const [descargando, setDescargando] = useState(false)

  const estado = m.estado
  const posibles = estado ? TRANSICIONES[estado] : []
  const siguiente = posibles.find((e) => e !== 'anulada')
  const puedeAnular = posibles.includes('anulada')

  const descargarPdf = async () => {
    setDescargando(true)
    try {
      const blob = await movimientoService.descargarPdf(Number(m.id))
      guardarArchivo(blob, `accion_personal_${m.codigo_registro ?? m.id}.pdf`)
    } catch (error) {
      notificar.error(
        'No se pudo generar el PDF de la acción de personal',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setDescargando(false)
    }
  }

  const avanzar = () => {
    // Un ingreso que pasa a registrada crea el contrato: se completan primero
    // sus datos en vez de fallar después.
    if (siguiente === 'registrada' && requiereCompletarVinculo(estado, m.tipo_movimiento)) {
      onCompletarVinculo()
      return
    }
    // Mismo criterio para el dictamen presupuestario: el backend rechaza
    // suscribir sin él, así que se pide antes en vez de dejar que la transición
    // falle.
    if (siguiente === 'suscrita' && tieneEfectoEconomico(m.tipo_movimiento)) {
      onPedirDictamen()
      return
    }
    if (siguiente) transicionar.mutate({ id: Number(m.id), estado: siguiente })
  }

  return (
    <ModalFooter
      onCancel={onClose}
      cancelLabel="Cerrar"
      sinPrincipal={!siguiente}
      submitLabel={siguiente ? `Pasar a ${ESTADO_LABELS[siguiente]}` : undefined}
      submitting={transicionar.isPending}
      onSubmit={siguiente ? avanzar : undefined}
      leftSection={
        <>
          {puedeDescargarPdf(estado, m.tipo_movimiento) && (
            <Button
              variant="subtle"
              leftSection={<IconFileDownload size={14} />}
              loading={descargando}
              onClick={descargarPdf}
            >
              PDF
            </Button>
          )}

          {puedeAnular && (
            <Button
              variant="subtle"
              color="red"
              leftSection={<IconBan size={14} />}
              onClick={() => confirmar({
                title:   'Anular acción de personal',
                message: 'Se anulará esta acción de personal y no podrá reactivarse.',
                destructiva: true,
                confirmLabel: 'Anular',
                onConfirm: () =>
                  transicionar.mutate({ id: Number(m.id), estado: 'anulada' }, { onSuccess: onClose }),
              })}
            >
              Anular
            </Button>
          )}
        </>
      }
    />
  )
}
