'use client'

import { Alert, Stack, Stepper, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useVacacionForm } from '../hooks/useVacacionForm'
import { RegistroConfirmado } from './RegistroConfirmado'
import { VacacionFechasCampos } from './VacacionFechasCampos'
import { VacacionSolicitanteCampos } from './VacacionSolicitanteCampos'
import { VacacionSolicitantePropio } from './VacacionSolicitantePropio'

interface Props {
  opened:  boolean
  onClose: () => void
  /**
   * Solo la solicitud propia, aunque quien la abra sea de Talento Humano. Lo
   * usa «Mis vacaciones» del portal; a cualquier servidor se le registra desde
   * Asistencia › Vacaciones.
   */
  soloPropio?: boolean
  /** Con `soloPropio`: cómo se llama quien pide, para el campo de solo lectura. */
  nombrePropio?: string
}

/**
 * Solicitud de vacaciones o de permiso con cargo a ellas, en dos pasos: los
 * datos y la confirmación con el folio.
 *
 * Tenía 639 líneas. El estado, el cálculo de días y el envío viven en
 * `useVacacionForm`; el esquema, en `vacacion.schema.ts`; los campos, en
 * `VacacionSolicitanteCampos` (Talento Humano) o `VacacionSolicitantePropio`
 * (el portal) y `VacacionFechasCampos`; y la confirmación, en
 * `RegistroConfirmado`, que comparte con permisos.
 */
export function VacacionModal({ opened, onClose, soloPropio = false, nombrePropio = '' }: Props) {
  const solicitud = useVacacionForm(onClose, soloPropio)
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

      {solicitud.paso === 0 && !solicitud.puedeRegistrar && (
        <Alert icon={<IconInfoCircle size={16} />} color="amber" variant="light">
          <Text size="sm">
            Su usuario no está vinculado a un servidor con unidad asignada, así
            que no hay a nombre de quién pedir las vacaciones. Pídale a Talento
            Humano que complete la vinculación.
          </Text>
        </Alert>
      )}

      {solicitud.paso === 0 && solicitud.puedeRegistrar && (
        <form onSubmit={solicitud.enviar} noValidate>
          <Stack gap="sm">
            {solicitud.propio ? (
              <VacacionSolicitantePropio
                form={solicitud.form}
                servidorId={solicitud.propio.id}
                nombrePropio={nombrePropio}
              />
            ) : (
              <VacacionSolicitanteCampos
                form={solicitud.form}
                unidadSelId={solicitud.unidadSelId}
                servidorSelId={solicitud.servidorSelId}
                onUnidad={solicitud.setUnidadSelId}
                onServidor={solicitud.setServidorSelId}
              />
            )}

            <VacacionFechasCampos form={solicitud.form} conteo={solicitud.conteo} />

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
