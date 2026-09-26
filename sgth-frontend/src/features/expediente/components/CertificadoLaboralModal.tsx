'use client'

import { useState } from 'react'
import { Alert, Radio, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { certificadoLaboralService } from '../services/certificadoLaboralService'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor: ServidorConRelaciones
}

const ES_SERVICIOS_PROFESIONALES = 'servicios_profesionales'

/**
 * Emite el certificado del servidor y lo descarga.
 *
 * Emitir no es previsualizar: cada vez que se pulsa, el backend deja
 * constancia con su código de verificación. Por eso el botón lo dice y el
 * aviso lo repite — dos clics son dos certificados en la bitácora.
 */
export function CertificadoLaboralModal({ opened, onClose, servidor }: Props) {
  const [conRemuneracion, setConRemuneracion] = useState('no')
  const [emitiendo, setEmitiendo] = useState(false)

  const esPrestacion = servidor.regimen_laboral === ES_SERVICIOS_PROFESIONALES
  const documento = esPrestacion
    ? 'certificado de prestación de servicios'
    : 'certificado laboral'

  const emitir = async () => {
    setEmitiendo(true)
    try {
      const { blob, codigo } = await certificadoLaboralService.emitir(
        Number(servidor.id),
        conRemuneracion === 'si',
      )

      guardarArchivo(blob, `certificado_${servidor.cedula ?? servidor.id}.pdf`)

      notificar.exito(
        'Certificado emitido',
        codigo
          ? `Quedó registrado con el código ${codigo}. Ya se puede comprobar en la página de verificación.`
          : 'El documento se descargó y quedó registrado.',
      )
      onClose()
    } catch (error) {
      notificar.error(
        'No se pudo emitir el certificado laboral',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setEmitiendo(false)
    }
  }

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Emitir certificado"
      size="md"
    >
      <Stack gap="md">
        <Text size="sm">
          Se emitirá un <strong>{documento}</strong> a nombre de{' '}
          {[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}.
        </Text>

        {esPrestacion && (
          <Alert variant="light" color="ocean">
            Su régimen es de servicios profesionales, que no es relación de
            dependencia: el documento lo dice así y no certifica tiempo de
            trabajo.
          </Alert>
        )}

        <Radio.Group
          label="¿Consta la remuneración?"
          description="Un crédito bancario suele pedirla; un visado o un concurso, no."
          value={conRemuneracion}
          onChange={setConRemuneracion}
        >
          <Stack gap="xs" mt="xs">
            <Radio value="no" label="Sin remuneración" />
            <Radio value="si" label="Con remuneración" />
          </Stack>
        </Radio.Group>

        <Alert
          variant="light"
          color="amber"
          icon={<IconAlertTriangle size={16} />}
        >
          Cada emisión queda registrada con su propio código y vence a los 30
          días. Emitir dos veces deja dos certificados en la bitácora.
        </Alert>
      </Stack>

      <ModalFooter
        onCancel={onClose}
        onSubmit={emitir}
        submitLabel="Emitir y descargar"
        submitting={emitiendo}
      />
    </SgthModal>
  )
}
