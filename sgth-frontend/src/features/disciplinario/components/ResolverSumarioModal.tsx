'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useForm, useWatch, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthModal, notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue } from '@/lib/fecha'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  resolucionSumarioSchema,
  type ResolucionSumarioFormData,
} from '../schemas/resolucionSumario.schema'
import { ResolucionSancionCampos } from './ResolucionSancionCampos'
import { nombreServidor } from '../utils/etiquetas'
import type { Sumario } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  sumario: Sumario | null
}

/**
 * Resolución del sumario: la autoridad nominadora da por probada una falta e
 * impone la sanción del Art. 43 de la LOSEP. Es el acto que cierra el
 * procedimiento y, si la sanción es la destitución, el que genera la Cesación
 * de Funciones en borrador para Talento Humano.
 */
export function ResolverSumarioModal({ opened, onClose, sumario }: Props) {
  // Sin sumario no hay formulario que armar, pero el modal sigue existiendo
  // para que su animación de cierre no se corte.
  if (!sumario) {
    return <SgthModal opened={opened} onClose={onClose} title="Resolver el sumario" />
  }

  // Se remonta al cambiar de sumario (key): así los valores iniciales son los
  // de ese sumario sin resetear estado desde un efecto.
  return (
    <FormularioResolucion
      key={sumario.id}
      opened={opened}
      onClose={onClose}
      sumario={sumario}
    />
  )
}

function FormularioResolucion({
  opened,
  onClose,
  sumario,
}: {
  opened: boolean
  onClose: () => void
  sumario: Sumario
}) {
  const { resolverSumario } = useDisciplinarioMutations()

  // `porcentaje_multa` y `dias_suspension` se OMITEN: dependen de la sanción
  // que se elija y arrancan vacíos (regla 09, nada de `as never`).
  const VALORES_INICIALES: DefaultValues<ResolucionSumarioFormData> = {
    fecha_efectiva: fromDateValue(new Date()),
    observaciones: '',
  }

  const {
    control,
    register,
    resetField,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ResolucionSumarioFormData>({
    resolver: zodResolver(resolucionSumarioSchema),
    defaultValues: VALORES_INICIALES,
  })

  const falta = useWatch({ control, name: 'tipo_falta' })
  const sancion = useWatch({ control, name: 'tipo_sancion' })

  const guardar = async (valores: ResolucionSumarioFormData) => {
    try {
      await resolverSumario.mutateAsync({
        id: sumario.id,
        data: {
          tipo_falta: valores.tipo_falta,
          tipo_sancion: valores.tipo_sancion,
          // Solo viaja la cifra de la sanción elegida: mandar las dos dejaría
          // en la base una multa con días de suspensión.
          porcentaje_multa: valores.tipo_sancion === 'multa'
            ? valores.porcentaje_multa ?? null
            : null,
          dias_suspension: valores.tipo_sancion === 'suspension'
            ? valores.dias_suspension ?? null
            : null,
          fecha_efectiva: valores.fecha_efectiva,
          observaciones: valores.observaciones.trim() || null,
        },
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in resolucionSumarioSchema.shape) {
          setError(campo as keyof ResolucionSumarioFormData, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo resolver el sumario', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Resolver el sumario e imponer la sanción"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Resolver sumario"
      submitting={resolverSumario.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          {nombreServidor(sumario.servidor)} — {sumario.motivo}
        </Text>

        <ResolucionSancionCampos
          control={control}
          register={register}
          resetField={resetField}
          errors={errors}
          falta={falta}
          sancion={sancion}
        />

        {sancion === 'destitucion' && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
            La destitución generará una Cesación de Funciones en borrador. El
            vínculo del servidor no se cierra aquí: Talento Humano debe revisarla
            y aprobarla desde Acciones de Personal.
          </Alert>
        )}
      </Stack>
    </FormModal>
  )
}
