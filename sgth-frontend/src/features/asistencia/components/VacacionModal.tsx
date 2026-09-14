'use client'

import { Stack, Stepper } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
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
  const solicitud = useVacacionForm(onClose)
  const { isSubmitting } = solicitud.form.formState

  return (
    <SgthModal
      opened={opened}
      onClose={solicitud.cerrar}
      title="Solicitud de vacaciones / permiso"
      size="xl"
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

          </Stack>

          <ModalFooter
            onCancel={solicitud.cerrar}
            submitLabel="Registrar solicitud"
            submitting={isSubmitting}
          />
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
    </SgthModal>
  )
}
