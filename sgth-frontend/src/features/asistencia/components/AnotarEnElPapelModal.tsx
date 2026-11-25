'use client'

import { useState } from 'react'
import { Alert, Stack, Text } from '@mantine/core'
import { IconPencil } from '@tabler/icons-react'
import { DetailList, ModalFooter, SgthModal } from '@/components/ui'
import type { AnotarEnElPapel } from '../services/asistenciaService'

interface Props {
  /** Lo que devolvió el registro. `null` cierra el panel. */
  anotar:  AnotarEnElPapel | null
  onClose: () => void
}

/** «18» y no «18.00»; «0.5» cuando el motivo descontó medio día. */
function dias(n: number): string {
  return `${Number(n.toFixed(2))} ${n === 1 ? 'día' : 'días'}`
}

/**
 * Lo que hay que escribir a mano en el impreso, al terminar de registrarlo.
 *
 * El papel llega a ventanilla firmado y con esas dos casillas en blanco, porque
 * cuando se imprimió todavía no se sabía de qué períodos iban a salir los días
 * ni con cuánto saldo quedaría el servidor: eso lo decide el registro. Así que
 * el sistema no las imprime, las dicta.
 *
 * Es un modal y no un aviso flotante a propósito: hay que transcribirlo con el
 * papel en la mano, y un aviso que se va solo a los cinco segundos obligaba a
 * entrar al detalle de la solicitud a buscar el dato otra vez.
 */
export function AnotarEnElPapelModal({ anotar, onClose }: Props) {
  // Lo último que hubo que anotar, que se conserva mientras el modal se
  // cierra: al pulsar «Listo» la pantalla pone `anotar` en null y las dos
  // casillas se iban antes que la ventana, así que el último fotograma era un
  // panel vacío. Se ajusta durante el renderizado y no en un efecto, para que
  // al abrirlo no haya un primer fotograma sin valores.
  const [mostrado, setMostrado] = useState(anotar)
  if (anotar !== null && anotar !== mostrado) setMostrado(anotar)

  return (
    <SgthModal
      opened={anotar !== null}
      onClose={onClose}
      title="Anote esto en el documento"
      size="md"
    >
      <Stack gap="md">
        <Alert variant="light" icon={<IconPencil size={18} />}>
          La solicitud quedó registrada y los días salieron del saldo del
          servidor. El impreso firmado trae estas dos casillas en blanco:
          escríbalas a mano antes de archivarlo.
        </Alert>

        {mostrado && (
          <DetailList
            columnas={1}
            items={[
              {
                label: 'Se descuenta de',
                value: <Text size="lg" fw={700}>{mostrado.se_descuenta_de}</Text>,
              },
              {
                label: 'Saldo restante',
                value: <Text size="lg" fw={700}>{dias(mostrado.saldo_restante)}</Text>,
              },
            ]}
          />
        )}
      </Stack>

      <ModalFooter onCancel={onClose} cancelLabel="Listo" sinPrincipal />
    </SgthModal>
  )
}
