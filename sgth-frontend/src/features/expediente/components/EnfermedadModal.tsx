'use client'

import { Stack, TextInput } from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { enfermedadSchema, type EnfermedadFormData }
  from '../schemas/enfermedad.schema'
import { DatePickerInput } from '@mantine/dates'
import { toDateValue, fromDateValueOrNull } from '@/lib/fecha'

interface Props {
  opened:     boolean
  onClose:    () => void
  /** Guarda el registro: con `id`, lo edita. El hook que lo hace ya notifica. */
  onGuardar:  (data: EnfermedadFormData, id?: number) => Promise<unknown>
  guardando:  boolean
  /** El padre monta el modal con `key` por registro: no hace falta `reset`. */
  initialValues?: {
    id:                  number
    tipo_enfermedad?:    string | null
    codigo_cie10?:       string | null
    fecha_diagnostico?:  string | null
  } | null
}

/**
 * Registrar o editar una enfermedad catastrófica, del servidor o de una carga
 * familiar. Antes eran dos modales y el del familiar no permitía editar.
 */
export function EnfermedadModal({ opened, onClose, onGuardar, guardando, initialValues }: Props) {
  const contained = useContainedInput()

  const { register, handleSubmit, reset, control, setError, formState: { errors } } =
    useForm<EnfermedadFormData>({
      resolver: zodResolver(enfermedadSchema),
      defaultValues: {
        tipo_enfermedad:   initialValues?.tipo_enfermedad ?? '',
        codigo_cie10:      initialValues?.codigo_cie10 ?? '',
        fecha_diagnostico: initialValues?.fecha_diagnostico?.split('T')[0] ?? null,
      },
    })

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: EnfermedadFormData) => {
    const data = {
      ...values,
      codigo_cie10: values.codigo_cie10 || null,
      fecha_diagnostico: values.fecha_diagnostico || null,
    }
    onGuardar(data, initialValues?.id).then(handleClose).catch((e) => erroresAlFormulario(
      e, setError, Object.keys(enfermedadSchema.shape), 'No se pudo guardar la enfermedad',
    ))
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? 'Editar enfermedad catastrófica' : 'Registrar enfermedad catastrófica'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={initialValues ? 'Guardar cambios' : 'Registrar enfermedad'}
      submitting={guardando}
    >
      <Stack gap="sm">
        <TextInput label="Nombre/Tipo de la enfermedad"
          placeholder="Ej: Insuficiencia renal crónica"
          {...contained} {...register('tipo_enfermedad')}
          error={errors.tipo_enfermedad?.message} />
        <TextInput label="Código CIE-10 (opcional)"
          placeholder="Ej: C18.0"
          maxLength={10}
          {...contained} {...register('codigo_cie10')}
          error={errors.codigo_cie10?.message} />
        <Controller
          name="fecha_diagnostico"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de diagnóstico"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              clearable
              // El backend no acepta un diagnóstico en el futuro.
              maxDate={new Date()}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_diagnostico?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
