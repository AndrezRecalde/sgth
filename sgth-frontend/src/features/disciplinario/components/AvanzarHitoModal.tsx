'use client'

import { Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  hitoSumarioSchema,
  type HitoSumarioFormData,
} from '../schemas/avanzarHito.schema'
import { ESTADO_SUMARIO_LABELS, nombreServidor } from '../utils/etiquetas'
import type { AvanzarSumarioData } from '../services/disciplinarioService'
import type { EstadoSumario, Sumario } from '@/types/api'

/**
 * Cada hito del sumario deja su fecha en una columna distinta, y de esas
 * fechas salen los plazos legales. La pantalla mandaba solo el estado, así que
 * las tres se guardaban con la de hoy —y el término del período de prueba, que
 * no tiene valor por omisión evidente, se quedaba en blanco—.
 */
const HITO = {
  en_instruccion: {
    campo: 'fecha_notificacion',
    etiqueta: 'Fecha de notificación al sumariado',
    descripcion: 'El día en que se le notificó el auto de apertura.',
    obligatoria: true,
  },
  en_prueba: {
    campo: 'fecha_termino_prueba',
    etiqueta: 'Fecha de término del período de prueba',
    descripcion: 'En blanco se cuentan 5 días hábiles desde la notificación.',
    obligatoria: false,
  },
  con_informe: {
    campo: 'fecha_informe',
    etiqueta: 'Fecha del informe del instructor',
    descripcion: 'Desde este día corren los 10 días hábiles para resolver.',
    obligatoria: true,
  },
} as const satisfies Partial<Record<EstadoSumario, unknown>>

/** Los hitos que piden fecha. Cerrar y apelar no la piden. */
export type HitoConFecha = keyof typeof HITO

export function esHitoConFecha(estado: EstadoSumario): estado is HitoConFecha {
  return estado in HITO
}

interface Props {
  opened: boolean
  onClose: () => void
  sumario: Sumario | null
  destino: HitoConFecha | null
}

export function AvanzarHitoModal({ opened, onClose, sumario, destino }: Props) {
  if (!sumario || !destino) {
    return <SgthModal opened={opened} onClose={onClose} title="Avanzar el sumario" />
  }

  // Se remonta al cambiar de sumario o de hito, así los valores iniciales son
  // los de ese avance sin resetear estado desde un efecto.
  return (
    <FormularioHito
      key={`${sumario.id}-${destino}`}
      opened={opened}
      onClose={onClose}
      sumario={sumario}
      destino={destino}
    />
  )
}

function FormularioHito({
  opened,
  onClose,
  sumario,
  destino,
}: {
  opened: boolean
  onClose: () => void
  sumario: Sumario
  destino: HitoConFecha
}) {
  const contained = useContainedInput()
  const { avanzarSumario } = useDisciplinarioMutations()
  const hito = HITO[destino]

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<HitoSumarioFormData>({
    resolver: zodResolver(hitoSumarioSchema(hito.obligatoria)),
    defaultValues: { fecha: hito.obligatoria ? fromDateValue(new Date()) : '' },
  })

  const guardar = async (valores: HitoSumarioFormData) => {
    const datos: AvanzarSumarioData = { estado: destino }
    datos[hito.campo] = valores.fecha || null

    try {
      await avanzarSumario.mutateAsync({ id: sumario.id, data: datos })
      onClose()
    } catch {
      // El hook ya lo notificó: el 422 de este formulario es de negocio
      // —una transición que el grafo no admite—, no de un campo.
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={`Avanzar a «${ESTADO_SUMARIO_LABELS[destino]}»`}
      onSubmit={handleSubmit(guardar)}
      submitLabel="Registrar avance"
      submitting={avanzarSumario.isPending}
      size="md"
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          {nombreServidor(sumario.servidor)} — estado actual:{' '}
          <strong>{ESTADO_SUMARIO_LABELS[sumario.estado]}</strong>
        </Text>

        <Controller
          name="fecha"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label={hito.etiqueta}
              description={hito.descripcion}
              valueFormat="DD/MM/YYYY"
              clearable={!hito.obligatoria}
              error={errors.fecha?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
