'use client'

import { Button, Group, Modal, Stack, Stepper } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useVacacionForm } from '../hooks/useVacacionForm'
import { RegistroConfirmado } from './RegistroConfirmado'
import { VacacionFechasCampos } from './VacacionFechasCampos'
import { VacacionSolicitanteCampos } from './VacacionSolicitanteCampos'

interface Props {
  opened:  boolean
  onClose: () => void
}

/**
 * Solicitud de vacaciones o de permiso con cargo a ellas, en dos pasos: los
 * datos y la confirmación con el folio.
 *
 * Tenía 639 líneas. El estado, el cálculo de días y el envío viven en
 * `useVacacionForm`; el esquema, en `vacacion.schema.ts`; los campos, en
 * `VacacionSolicitanteCampos` y `VacacionFechasCampos`; y la confirmación, en
 * `RegistroConfirmado`, que comparte con permisos.
 */
export function VacacionModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const solicitud = useVacacionForm(onClose)
  const { isSubmitting } = solicitud.form.formState

  return (
    <Modal
      opened={opened}
      onClose={solicitud.cerrar}
      title="Solicitud de vacaciones / permiso"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stepper active={solicitud.paso} mb="lg" size="sm">
        <Stepper.Step label="Datos de la solicitud" />
        <Stepper.Step label="Confirmación" />
      </Stepper>

      {solicitud.paso === 0 && (
        <form onSubmit={solicitud.enviar} noValidate>
          <Stack gap="sm">
            <VacacionSolicitanteCampos
              form={solicitud.form}
              unidadSelId={solicitud.unidadSelId}
              servidorSelId={solicitud.servidorSelId}
              onUnidad={solicitud.setUnidadSelId}
              onServidor={solicitud.setServidorSelId}
            />

            <VacacionFechasCampos form={solicitud.form} />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={solicitud.cerrar}>
                Cancelar
              </Button>
              <Button type="submit" color="emerald" variant="light" loading={isSubmitting}>
                Registrar solicitud
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      {solicitud.paso === 1 && solicitud.vacacionCreada && (
        <RegistroConfirmado
          titulo="Solicitud registrada correctamente"
          folio={solicitud.vacacionCreada.folio}
          pregunta="¿Desea exportar la solicitud en PDF?"
          exportando={solicitud.exportando}
          onExportar={solicitud.exportarCreada}
          onCerrar={solicitud.cerrar}
        />
      )}
    </Modal>
  )
}
