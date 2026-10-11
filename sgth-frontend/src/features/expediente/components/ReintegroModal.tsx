'use client'

import { Alert, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha, fromDateValue, hoyIso, sumarDias, toDateValue } from '@/lib/fecha'
import { useReintegrarAusencia } from '../hooks/useAusenciasTemporales'
import { reintegroSchema, type ReintegroFormData } from '../schemas/reintegro.schema'
import type { AusenciaTemporal } from '../services/ausenciaTemporalService'

interface Props {
  opened: boolean
  onClose: () => void
  ausencia: AusenciaTemporal | null
}

/**
 * El reintegro nace de la ausencia que cierra (fase 2.4): el servidor vuelve
 * de su comisión o su licencia. Se prepara en borrador y sigue en la bandeja
 * el trámite de cualquier acción; al surtir efecto la ausencia termina la
 * víspera del regreso y, si alguien la cubría, se le prepara la salida.
 */
export function ReintegroModal({ opened, onClose, ausencia }: Props) {
  if (!ausencia) {
    return <SgthModal opened={opened} onClose={onClose} title="Reintegrar" />
  }

  // Se remonta al cambiar de ausencia, así los valores iniciales son los de
  // esa fila sin resetear estado desde un efecto.
  return <FormularioReintegro key={ausencia.id} opened={opened} onClose={onClose} ausencia={ausencia} />
}

function FormularioReintegro({
  opened,
  onClose,
  ausencia,
}: {
  opened: boolean
  onClose: () => void
  ausencia: AusenciaTemporal
}) {
  const contained = useContainedInput()
  const reintegrar = useReintegrarAusencia()

  // El regreso cae después del inicio y a más tardar el día siguiente al fin:
  // el mismo rango que valida el backend.
  const primero = ausencia.desde ? sumarDias(ausencia.desde, 1) : null
  const ultimo = ausencia.hasta ? sumarDias(ausencia.hasta, 1) : null
  const hoy = hoyIso()
  const sugerida = ultimo && hoy > ultimo ? ultimo : primero && hoy < primero ? primero : hoy

  const {
    control,
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ReintegroFormData>({
    resolver: zodResolver(reintegroSchema),
    defaultValues: {
      fecha_regreso: sugerida,
      descripcion: `Reintegro a sus funciones al término de la ${ausencia.etiqueta ?? 'ausencia'}`
        + (ausencia.codigo_registro ? ` (${ausencia.codigo_registro})` : '')
        + '.',
    },
  })

  const guardar = async (valores: ReintegroFormData) => {
    try {
      await reintegrar.mutateAsync({ ausenciaId: ausencia.id, data: valores })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (campos?.fecha_regreso) setError('fecha_regreso', { type: 'server', message: campos.fecha_regreso })
      if (campos?.descripcion) setError('descripcion', { type: 'server', message: campos.descripcion })
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Reintegrar al servidor"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Preparar reintegro"
      submitting={reintegrar.isPending}
      size="md"
    >
      <Stack gap="md">
        <div>
          <Text size="sm" fw={600}>{ausencia.servidor.nombre || '—'}</Text>
          <Text size="xs" c="dimmed">
            {ausencia.etiqueta ?? 'Ausencia'} · del {formatFecha(ausencia.desde)}
            {ausencia.hasta ? ` al ${formatFecha(ausencia.hasta)}` : ', sin fecha de fin'}
          </Text>
        </div>

        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Se prepara en borrador. Cuando surta efecto, la ausencia termina el día
          anterior al regreso
          {ausencia.reemplazo ? ', y a quien la cubre se le prepara la salida.' : '.'}
        </Alert>

        <Controller
          name="fecha_regreso"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de regreso"
              description="El primer día en que vuelve a su puesto."
              required
              valueFormat="DD/MM/YYYY"
              minDate={toDateValue(primero) ?? undefined}
              maxDate={toDateValue(ultimo) ?? undefined}
              // El calendario se abre sobre un modal: hay que levantarlo por
              // encima de la pila de capas que ya hay.
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              error={errors.fecha_regreso?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />

        <Textarea
          label="Explicación"
          description="Es el texto que lleva el documento de la acción de personal."
          required
          autosize
          minRows={3}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
