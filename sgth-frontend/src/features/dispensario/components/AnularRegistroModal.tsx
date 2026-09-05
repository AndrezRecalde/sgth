'use client'

import {
  Modal, Stack, Select, Textarea,
  Button, Group, Text, Alert,
} from '@mantine/core'
import { useState } from 'react'
import { IconAlertTriangle, IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

interface MotivoOption {
  value: string
  label: string
}

interface Props {
  opened:      boolean
  onClose:     () => void
  titulo:      string
  descripcion: string
  onConfirmar: (motivo: string) => void
  loading:     boolean
  /**
   * Motivos ofrecidos. Por defecto los de un registro clínico; una farmacia
   * anula por otras razones —cantidades mal digitadas, un paciente que no
   * volvió— y ahí sobra preguntar por el diagnóstico.
   */
  motivos?:    MotivoOption[]
}

const MOTIVOS_CLINICOS: MotivoOption[] = [
  { value: 'duplicado',            label: 'Registro duplicado'       },
  { value: 'error_digitacion',     label: 'Error de digitación'      },
  { value: 'diagnostico_incorrecto', label: 'Diagnóstico incorrecto' },
  { value: 'paciente_incorrecto',  label: 'Paciente incorrecto'      },
  { value: 'otro',                 label: 'Otro'                     },
]

export const MOTIVOS_ANULAR_ADQUISICION: MotivoOption[] = [
  { value: 'error_digitacion',    label: 'Error de digitación'          },
  { value: 'cantidades',          label: 'Cantidades incorrectas'       },
  { value: 'documento',           label: 'Documento o proveedor errado' },
  { value: 'duplicado',           label: 'Registro duplicado'           },
  { value: 'otro',                label: 'Otro'                         },
]

export const MOTIVOS_ANULAR_ATENCION: MotivoOption[] = [
  { value: 'paciente_incorrecto', label: 'Paciente incorrecto'      },
  { value: 'servicio_incorrecto', label: 'Servicio incorrecto'      },
  { value: 'no_se_realizo',       label: 'El servicio no se realizó' },
  { value: 'error_digitacion',    label: 'Error de digitación'      },
  { value: 'duplicado',           label: 'Registro duplicado'       },
  { value: 'otro',                label: 'Otro'                     },
]

export const MOTIVOS_ANULAR_CERTIFICADO: MotivoOption[] = [
  { value: 'diagnostico_corregido', label: 'Diagnóstico corregido'   },
  { value: 'dias_incorrectos',      label: 'Días de reposo erróneos' },
  { value: 'paciente_incorrecto',   label: 'Paciente incorrecto'     },
  { value: 'error_digitacion',      label: 'Error de digitación'     },
  { value: 'duplicado',             label: 'Certificado duplicado'   },
  { value: 'otro',                  label: 'Otro'                    },
]

export const MOTIVOS_ANULAR_RECETA: MotivoOption[] = [
  { value: 'no_retiro',        label: 'El paciente no retiró'   },
  { value: 'cambio_tratamiento', label: 'Cambio de tratamiento' },
  { value: 'error_digitacion', label: 'Error de digitación'     },
  { value: 'paciente_incorrecto', label: 'Paciente incorrecto'  },
  { value: 'otro',             label: 'Otro'                    },
]

export function AnularRegistroModal({
  opened, onClose, titulo, descripcion,
  onConfirmar, loading,
  motivos = MOTIVOS_CLINICOS,
}: Props) {
  const contained = useContainedInput()
  const { isMobile } = useMobileBreakpoint()
  const [motivoSel, setMotivoSel] = useState<string>('')
  const [motivoLibre, setMotivoLibre] = useState('')

  const motivoFinal = motivoSel === 'otro'
    ? motivoLibre.trim()
    : motivos.find(m => m.value === motivoSel)?.label ?? ''

  const puedeConfirmar = !!motivoSel &&
    (motivoSel !== 'otro' || motivoLibre.trim().length >= 5)

  const handleConfirmar = () => {
    if (!puedeConfirmar) return
    onConfirmar(motivoFinal)
  }

  const handleClose = () => {
    setMotivoSel('')
    setMotivoLibre('')
    onClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={titulo}
      size="sm"
      radius={isMobile ? 0 : 'xl'}
      // A pantalla completa en móvil: centrado, con el aviso, el select y el
      // textarea de «otro motivo», el diálogo se comía la pantalla y dejaba los
      // botones fuera de alcance.
      fullScreen={isMobile}
    >
      <Stack gap="sm">
        <Alert
          icon={<IconAlertTriangle size={14} />}
          color="orange"
          variant="light"
        >
          <Text size="xs">
            <Text span fw={600}>{descripcion}</Text>
            {' '}Esta acción no se puede deshacer.
            El registro quedará anulado con trazabilidad.
          </Text>
        </Alert>

        <Select
          label="Motivo de anulación"
          placeholder="Seleccione un motivo"
          data={motivos}
          {...contained}
          value={motivoSel}
          onChange={(v) => {
            setMotivoSel(v ?? '')
            if (v !== 'otro') setMotivoLibre('')
          }}
        />

        {motivoSel === 'otro' && (
          <Textarea
            label="Especifique el motivo"
            placeholder="Mínimo 5 caracteres"
            autosize
            minRows={2}
            {...contained}
            value={motivoLibre}
            onChange={(e) => setMotivoLibre(e.currentTarget.value)}
          />
        )}

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color="orange"
            leftSection={<IconCheck size={14} />}
            disabled={!puedeConfirmar}
            loading={loading}
            onClick={handleConfirmar}
          >
            Confirmar anulación
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
