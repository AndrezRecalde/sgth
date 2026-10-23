'use client'

import { Alert, Checkbox, FileInput, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { hoyIso } from '@/lib/fecha'
import { porEnviar } from '../constants/misExamenes'
import { useMisExamenesMutations } from '../hooks/useMisExamenes'
import { ARCHIVO_MAX_MB, envioResultadoSchema, type EnvioResultadoFormData } from '../schemas/envioResultado.schema'
import type { MiOrdenExamen } from '../services/misExamenesService'

interface Props {
  /** La orden a la que se envía; sin ella el modal está cerrado. */
  orden:   MiOrdenExamen | null
  onClose: () => void
}

/**
 * Enviar al Dispensario el PDF o la foto del laboratorio, marcando qué
 * exámenes de la orden trae. Queda por validar hasta que un médico lo acepte.
 */
export function EnviarResultadoModal({ orden, onClose }: Props) {
  return orden ? <Formulario orden={orden} onClose={onClose} /> : null
}

function Formulario({ orden, onClose }: { orden: MiOrdenExamen; onClose: () => void }) {
  const contained = useContainedInput()
  const { enviar } = useMisExamenesMutations()
  const disponibles = porEnviar(orden)

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<EnvioResultadoFormData>({
    resolver: zodResolver(envioResultadoSchema),
    defaultValues: {
      // Con un solo examen no hay nada que elegir.
      items:           disponibles.length === 1 ? [disponibles[0].id] : [],
      fecha_resultado: hoyIso(),
      comentario:      '',
      archivo:         null,
    },
  })

  const guardar = (v: EnvioResultadoFormData) => {
    if (!v.archivo) return
    enviar.mutateAsync({
      ordenId: orden.id,
      envio:   { items: v.items, fecha_resultado: v.fecha_resultado, comentario: v.comentario, archivo: v.archivo },
    }).then(onClose).catch((e) => erroresAlFormulario(
      e, setError, Object.keys(envioResultadoSchema.shape), 'No se pudo enviar el resultado al Dispensario',
    ))
  }

  return (
    <FormModal opened onClose={onClose} title={`Enviar resultado · Orden ${orden.folio}`} size="lg"
      onSubmit={handleSubmit(guardar)} submitLabel="Enviar al Dispensario" submitting={enviar.isPending}>
      <Stack gap="sm">
        <Alert variant="light" icon={<IconInfoCircle size={16} />}>
          Un médico del Dispensario lo revisará antes de agregarlo a su historia clínica. Hasta entonces puede retirarlo.
        </Alert>

        <Controller name="items" control={control} render={({ field }) => (
          <Checkbox.Group label="¿Qué exámenes trae el archivo?" withAsterisk
            value={field.value.map(String)} onChange={(v) => field.onChange(v.map(Number))}
            error={errors.items?.message}>
            <Stack gap={6} mt={6}>
              {disponibles.map((i) => (
                <Checkbox key={i.id} value={String(i.id)}
                  label={`${i.nombre}${i.nota ? ` (${i.nota})` : ''}`}
                  description={i.estado === 'no_realizado' ? 'El Dispensario lo había marcado como no realizado' : undefined} />
              ))}
            </Stack>
          </Checkbox.Group>
        )} />

        <Controller name="fecha_resultado" control={control} render={({ field }) => (
          // Ni futura ni anterior a la orden, como en el backend.
          <DatePickerInput label="Fecha del resultado" description="La que dice la hoja del laboratorio"
            valueFormat="DD/MM/YYYY" minDate={orden.fecha_solicitud} maxDate={new Date()} withAsterisk
            {...contained} value={field.value || null} onChange={(v) => field.onChange(v ?? '')}
            error={errors.fecha_resultado?.message} />
        )} />

        <Controller name="archivo" control={control} render={({ field }) => (
          <FileInput label="Archivo" placeholder="Elegir el PDF o la foto…" withAsterisk
            description={`PDF, JPG o PNG — máximo ${ARCHIVO_MAX_MB} MB. Si es una foto, que se lea toda la hoja.`}
            accept="application/pdf,image/jpeg,image/png" {...contained}
            value={field.value} onChange={field.onChange} error={errors.archivo?.message} />
        )} />

        <Textarea label="Comentario para el médico" placeholder="Ej.: me lo hice en otro laboratorio"
          autosize minRows={2} maxLength={300} {...contained} {...register('comentario')}
          error={errors.comentario?.message} />

        {disponibles.length < (orden.items ?? []).length && (
          <Text size="xs" c="dimmed">
            Los exámenes que no aparecen ya tienen su resultado en el Dispensario o esperan uno que usted envió.
          </Text>
        )}
      </Stack>
    </FormModal>
  )
}
