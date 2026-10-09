'use client'

import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import { Button } from '@mantine/core'
import { IconBan, IconFileDownload } from '@tabler/icons-react'
import { ModalFooter, MotivoModal, notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { movimientoService } from '../services/movimientoService'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import {
  ESTADO_LABELS, TRANSICIONES, puedeDescargarPdf, requiereCompletarVinculo,
} from '../utils/estadoAccionPersonal'
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
  const [anularOpened, { open: abrirAnular, close: cerrarAnular }] = useDisclosure(false)

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
        getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'),
      )
    } finally {
      setDescargando(false)
    }
  }

  const avanzar = () => {
    // Un ingreso que pasa a registrada crea el contrato: se completan primero
    // sus datos en vez de fallar después.
    if (siguiente === 'registrada' && requiereCompletarVinculo(estado, m.clase)) {
      onCompletarVinculo()
      return
    }
    // Mismo criterio para el dictamen presupuestario: el backend rechaza
    // suscribir sin él, así que se pide antes en vez de dejar que la transición
    // falle.
    if (siguiente === 'suscrita' && m.tiene_efecto_economico) {
      onPedirDictamen()
      return
    }
    if (siguiente) transicionar.mutate({ id: Number(m.id), estado: siguiente })
  }

  const pie = (
    <ModalFooter
      onCancel={onClose}
      cancelLabel="Cerrar"
      sinPrincipal={!siguiente}
      submitLabel={siguiente ? `Pasar a ${ESTADO_LABELS[siguiente]}` : undefined}
      submitting={transicionar.isPending}
      onSubmit={siguiente ? avanzar : undefined}
      leftSection={
        <>
          {puedeDescargarPdf(m) && (
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
              onClick={abrirAnular}
            >
              Anular
            </Button>
          )}
        </>
      }
    />
  )

  /*
  | Anular pide el motivo, como en Permisos, Vacaciones y Viáticos. Antes era un
  | `confirmar()` de sí o no, y anular un acto administrativo no dejaba ni una
  | línea que explicara la decisión: el expediente se quedaba con una acción en
  | 'anulada' y nadie sabía por qué. El backend lo exige con `required_if`.
  |
  | Desde que se puede anular lo ya registrado, el aviso distingue los dos
  | casos: anular un borrador no deshace nada, y anular un acto registrado SÍ
  | revierte lo que hizo sobre el vínculo del servidor. Quien anula tiene que
  | saber cuál de las dos cosas está a punto de pasar.
  */
  const yaSurtioEfecto = Boolean(m.codigo_registro) && m.toca_el_vinculo

  return (
    <>
      {pie}

      <MotivoModal
        opened={anularOpened}
        onClose={cerrarAnular}
        title="Anular acción de personal"
        descripcion={
          <>
            Se anulará {m.codigo_registro ? <b>{m.codigo_registro}</b> : 'esta acción de personal'}
            {' '}y no podrá reactivarse. El motivo queda en el expediente.
            {yaSurtioEfecto && (
              <>
                {' '}Como ya está registrada, <b>se deshará su efecto sobre el vínculo
                del servidor</b> y volverá a la situación anterior. Si lo que
                quiere es rectificar, anule esta y registre una nueva.
              </>
            )}
          </>
        }
        confirmLabel="Anular"
        destructiva
        cargando={transicionar.isPending}
        onConfirm={(motivo) => transicionar.mutate(
          { id: Number(m.id), estado: 'anulada', motivo_anulacion: motivo },
          { onSuccess: () => { cerrarAnular(); onClose() } },
        )}
      />
    </>
  )
}
