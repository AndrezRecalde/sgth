'use client'

import { Alert, Grid, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { useRegistrarSubrogacionPorLey } from '../hooks/useAutoridadesElectas'
import { subrogacionPorLeySchema, type SubrogacionPorLeyFormData } from '../schemas/subrogacionPorLey.schema'

const MOTIVOS = [
  { value: 'vacaciones', label: 'Vacaciones' },
  { value: 'licencia', label: 'Licencia' },
  { value: 'enfermedad', label: 'Enfermedad' },
  { value: 'comision_servicios', label: 'Comisión de servicios' },
  { value: 'otro', label: 'Otro' },
]

interface Props {
  opened: boolean
  onClose: () => void
}

/**
 * La subrogación por ley del Viceprefecto (COOTAD Art. 51; fase 4.1b): si la
 * ausencia del Prefecto pasa de tres días, lo subroga y recibe su remuneración.
 * La de una licencia del Consejo se prepara sola; esta es para las vacaciones u
 * otras ausencias. Quién subroga a quién lo decide el sistema.
 */
export function SubrogacionPorLeyModal({ opened, onClose }: Props) {
  // Se monta al abrir: el formulario arranca limpio sin resetear desde un efecto.
  return opened ? <FormularioSubrogacionPorLey onClose={onClose} /> : null
}

function FormularioSubrogacionPorLey({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const registrar = useRegistrarSubrogacionPorLey()

  const {
    control,
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<SubrogacionPorLeyFormData>({
    resolver: zodResolver(subrogacionPorLeySchema),
    defaultValues: { fecha_inicio: '', fecha_fin: '', motivo: 'vacaciones', resolucion_numero: '', observacion: '' },
  })

  const guardar = async (valores: SubrogacionPorLeyFormData) => {
    try {
      await registrar.mutateAsync({ ...valores, observacion: valores.observacion?.trim() || null })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of ['fecha_inicio', 'fecha_fin', 'motivo', 'resolucion_numero'] as const) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
    }
  }

  const fecha = (name: 'fecha_inicio' | 'fecha_fin', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          required
          valueFormat="DD/MM/YYYY"
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          error={errors[name]?.message}
          {...contained}
          value={toDateValue(field.value)}
          onChange={(v) => field.onChange(fromDateValue(v))}
        />
      )}
    />
  )

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Subrogación por ley del Viceprefecto"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Preparar subrogación"
      submitting={registrar.isPending}
      size="md"
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Si la ausencia del Prefecto pasa de tres días, el Viceprefecto lo subroga
          y recibe su remuneración (COOTAD Art. 51). Se prepara en borrador; al
          registrarse, firma como autoridad nominadora «(S)» mientras dure.
        </Alert>

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_inicio', 'Desde')}</Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_fin', 'Hasta')}</Grid.Col>
        </Grid>

        <Controller
          name="motivo"
          control={control}
          render={({ field }) => (
            <Select
              label="Motivo de la ausencia"
              required
              data={MOTIVOS}
              value={field.value}
              onChange={(v) => field.onChange(v ?? '')}
              error={errors.motivo?.message}
              {...contained}
            />
          )}
        />

        <TextInput
          label="Oficio o resolución N.º"
          description="El oficio que comunica la ausencia o la resolución del Consejo."
          required
          error={errors.resolucion_numero?.message}
          {...contained}
          {...register('resolucion_numero')}
        />

        <Textarea
          label="Observación"
          placeholder="Opcional"
          autosize
          minRows={2}
          error={errors.observacion?.message}
          {...contained}
          {...register('observacion')}
        />
      </Stack>
    </FormModal>
  )
}
