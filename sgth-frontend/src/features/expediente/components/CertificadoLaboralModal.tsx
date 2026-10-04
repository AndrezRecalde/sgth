'use client'

import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Radio, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconHistory } from '@tabler/icons-react'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import {
  CERTIFICADOS_EMITIDOS_KEY,
  useCertificadosEmitidos,
} from '../hooks/useCertificadosEmitidos'
import { certificadoLaboralService } from '../services/certificadoLaboralService'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor: ServidorConRelaciones
}

const ES_SERVICIOS_PROFESIONALES = 'servicios_profesionales'

/** «hace 3 días» se lee de un vistazo; una fecha hay que restarla mentalmente. */
function diasDesde(fecha: string | null): number | null {
  if (!fecha) return null
  const dias = Math.floor(
    (Date.now() - new Date(fecha).getTime()) / (1000 * 60 * 60 * 24),
  )
  return Number.isFinite(dias) ? Math.max(dias, 0) : null
}

function haceCuanto(dias: number | null): string {
  if (dias === null) return ''
  if (dias === 0) return 'hoy mismo'
  if (dias === 1) return 'ayer'
  return `hace ${dias} días`
}

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
  const queryClient = useQueryClient()

  // Solo con el modal abierto: la bitácora no hace falta hasta que alguien se
  // plantea emitir.
  const { data: emisiones } = useCertificadosEmitidos(Number(servidor.id), opened)

  // El más reciente, si todavía está vigente. Uno vencido no evita nada: la
  // persona no puede presentarlo, así que volver a emitir es lo correcto.
  const reciente = emisiones?.[0]?.vigente ? emisiones[0] : undefined

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

      queryClient.invalidateQueries({
        queryKey: [CERTIFICADOS_EMITIDOS_KEY, Number(servidor.id)],
      })

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
        getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'),
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

        {/* Emitir a ciegas se pagaba dos veces: repetir el trabajo y no poder
            explicar por qué la persona vuelve a pedirlo. El dato ya estaba en
            la bitácora y no se enseñaba en ninguna pantalla. */}
        {reciente && (
          <Alert
            variant="light"
            color="ocean"
            icon={<IconHistory size={16} />}
            title={`Ya se emitió uno ${haceCuanto(diasDesde(reciente.emitido_en))}`}
          >
            {reciente.con_remuneracion ? 'Con remuneración' : 'Sin remuneración'}
            , código {reciente.codigo}, vigente hasta el{' '}
            {formatFecha(reciente.vence_en)}. Si la persona todavía lo tiene, no
            hace falta emitir otro.
          </Alert>
        )}

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
