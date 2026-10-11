'use client'

import { Alert, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha, fromDateValue, hoyIso, sumarDias, toDateValue } from '@/lib/fecha'
import { useReintegrarAscenso } from '../hooks/usePeriodosDePrueba'
import { reintegroAscensoSchema, type ReintegroAscensoFormData } from '../schemas/reintegroAscenso.schema'
import type { PeriodoDePrueba } from '../services/periodoDePruebaService'

interface Props {
  opened: boolean
  onClose: () => void
  periodo: PeriodoDePrueba | null
}

/**
 * El ascendido que no superó la prueba vuelve a su puesto anterior con su
 * remuneración anterior (LOSEP 17 b.5; fase 3.3). Se prepara en borrador y
 * sigue en la bandeja el trámite de cualquier acción; al surtir efecto el
 * servidor vuelve a ser permanente en el puesto que se le reservaba.
 */
export function ReintegroAscensoModal({ opened, onClose, periodo }: Props) {
  if (!periodo) {
    return <SgthModal opened={opened} onClose={onClose} title="Reintegrar al puesto anterior" />
  }

  // Se remonta al cambiar de fila, así los valores iniciales son los de esa
  // prueba sin resetear estado desde un efecto.
  return <FormularioReintegroAscenso key={periodo.contrato_id} opened={opened} onClose={onClose} periodo={periodo} />
}

function FormularioReintegroAscenso({
  opened,
  onClose,
  periodo,
}: {
  opened: boolean
  onClose: () => void
  periodo: PeriodoDePrueba
}) {
  const contained = useContainedInput()
  const reintegrar = useReintegrarAscenso()

  // El regreso cae después del ascenso y a más tardar el día siguiente al fin
  // de la prueba: el mismo rango que valida el backend.
  const primero = periodo.desde ? sumarDias(periodo.desde, 1) : null
  const ultimo = periodo.hasta ? sumarDias(periodo.hasta, 1) : null
  const hoy = hoyIso()
  const sugerida = ultimo && hoy > ultimo ? ultimo : primero && hoy < primero ? primero : hoy

  const {
    control,
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ReintegroAscensoFormData>({
    resolver: zodResolver(reintegroAscensoSchema),
    defaultValues: {
      fecha_regreso: sugerida,
      informe_uath_numero: '',
      descripcion: 'Reintegro a su puesto anterior, con su remuneración anterior, por no haber superado la '
        + 'evaluación del período de prueba del ascenso (Art. 17 literal b.5 de la Ley Orgánica de Servicio Público).',
    },
  })

  const guardar = async (valores: ReintegroAscensoFormData) => {
    try {
      await reintegrar.mutateAsync({ contratoId: periodo.contrato_id, data: valores })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (campos?.fecha_regreso) setError('fecha_regreso', { type: 'server', message: campos.fecha_regreso })
      if (campos?.informe_uath_numero) {
        setError('informe_uath_numero', { type: 'server', message: campos.informe_uath_numero })
      }
      if (campos?.descripcion) setError('descripcion', { type: 'server', message: campos.descripcion })
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Reintegrar al puesto anterior"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Preparar reintegro"
      submitting={reintegrar.isPending}
      size="md"
    >
      <Stack gap="md">
        <div>
          <Text size="sm" fw={600}>{periodo.servidor.nombre || '—'}</Text>
          <Text size="xs" c="dimmed">
            Ascenso a {periodo.puesto ?? '—'} · prueba del {formatFecha(periodo.desde)} al {formatFecha(periodo.hasta)}
          </Text>
        </div>

        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Se prepara en borrador. Cuando surta efecto, el servidor vuelve a
          {' '}{periodo.puesto_reservado ?? 'su puesto anterior'}, con su remuneración
          anterior y su nombramiento permanente.
        </Alert>

        <Controller
          name="fecha_regreso"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de regreso"
              description="El primer día en su puesto anterior."
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

        <TextInput
          label="Informe de la evaluación N.º"
          description="La ley condiciona el reintegro a una evaluación técnica y objetiva de sus servicios."
          placeholder="Ej. INF-EVAL-2026-003"
          required
          error={errors.informe_uath_numero?.message}
          {...contained}
          {...register('informe_uath_numero')}
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
