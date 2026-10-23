'use client'

import { Button, Checkbox, Group, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconExternalLink } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useEnviosResultado } from '../../hooks/useEnviosResultado'
import { validarEnvioSchema, type ValidarEnvioFormData } from '../../schemas/validarEnvio.schema'
import { pacienteDeOrden } from '../../services/bandejaExamenesService'
import { TIPO_RESULTADO_OPTIONS } from '../../services/resultadoMedicoService'
import { descripcionSugerida, tipoSugerido } from '../../utils/resultadoDeOrden'
import type { EnvioPorValidar } from '../../services/envioResultadoService'

interface Props {
  /** El envío que se valida; sin él el modal está cerrado. */
  envio:   EnvioPorValidar | null
  onClose: () => void
}

/**
 * Validar lo que el paciente envió desde el Portal (D2): el médico mira el
 * archivo, confirma qué exámenes trae y lo registra. Nace el resultado de la
 * historia clínica, por revisar por quien pidió la orden (D3).
 */
export function ValidarEnvioModal({ envio, onClose }: Props) {
  return envio ? <Formulario envio={envio} onClose={onClose} /> : null
}

function Formulario({ envio, onClose }: { envio: EnvioPorValidar; onClose: () => void }) {
  const contained = useContainedInput()
  const { validar, verArchivo } = useEnviosResultado()
  const orden = envio.orden_examen
  const paciente = pacienteDeOrden(orden)
  // Lo que todavía espera resultado; lo que marcó el paciente, de entrada.
  const disponibles = orden.items.filter((i) => i.estado !== 'recibido')
  const propuestos = envio.items.filter((id) => disponibles.some((i) => i.id === id))
  const marcadosDe = (ids: number[]) => orden.items.filter((i) => ids.includes(i.id))

  const { control, register, handleSubmit, setValue, setError, formState: { errors, dirtyFields } } =
    useForm<ValidarEnvioFormData>({
      resolver: zodResolver(validarEnvioSchema),
      defaultValues: {
        items:           propuestos,
        tipo:            tipoSugerido(marcadosDe(propuestos)),
        descripcion:     descripcionSugerida(marcadosDe(propuestos)),
        fecha_resultado: envio.fecha_resultado.slice(0, 10),
      },
    })

  // El tipo y la descripción se proponen a partir de lo marcado, hasta que se escriban a mano.
  const elegirItems = (ids: number[]) => {
    setValue('items', ids, { shouldValidate: errors.items !== undefined })
    if (!dirtyFields.tipo) setValue('tipo', tipoSugerido(marcadosDe(ids)))
    if (!dirtyFields.descripcion) setValue('descripcion', descripcionSugerida(marcadosDe(ids)))
  }

  const guardar = (v: ValidarEnvioFormData) =>
    validar.mutateAsync({ id: envio.id, data: { ...v, descripcion: v.descripcion.trim() } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(
        e, setError, Object.keys(validarEnvioSchema.shape), 'No se pudo validar el resultado del Portal',
      ))

  return (
    <FormModal opened onClose={onClose} title={`Validar resultado del Portal · Orden ${orden.folio}`} size="lg"
      onSubmit={handleSubmit(guardar)} submitLabel="Validar y agregar a la historia" submitting={validar.isPending}>
      <Stack gap="sm">
        <Group justify="space-between" align="flex-start" wrap="nowrap">
          <Stack gap={0}>
            <Text size="sm" fw={500}>{paciente.nombre}</Text>
            <Text size="xs" c="dimmed">{paciente.cedula ?? ''}</Text>
            {envio.comentario && <Text size="xs" mt={4}>Comentario del paciente: «{envio.comentario}»</Text>}
          </Stack>
          <Button size="xs" variant="light" leftSection={<IconExternalLink size={14} />}
            loading={verArchivo.isPending} onClick={() => verArchivo.mutate(envio.id)}>
            Ver archivo
          </Button>
        </Group>

        <Controller name="items" control={control} render={({ field }) => (
          <Checkbox.Group label="Exámenes que trae el archivo" withAsterisk
            description="Los marcó el paciente: corríjalos si el archivo trae otros."
            value={field.value.map(String)} onChange={(v) => elegirItems(v.map(Number))}
            error={errors.items?.message}>
            <Stack gap={6} mt={6}>
              {disponibles.map((i) => (
                <Checkbox key={i.id} value={String(i.id)}
                  label={`${i.examen?.nombre ?? 'Examen retirado del catálogo'}${i.nota ? ` (${i.nota})` : ''}`} />
              ))}
            </Stack>
          </Checkbox.Group>
        )} />

        <Controller name="tipo" control={control} render={({ field }) => (
          <Select label="Tipo de resultado" placeholder="Seleccione" withAsterisk {...contained}
            data={TIPO_RESULTADO_OPTIONS} value={field.value || null}
            onChange={(v) => field.onChange(v ?? '')} error={errors.tipo?.message} />
        )} />

        <Textarea label="Descripción" withAsterisk autosize minRows={2} maxLength={500} {...contained}
          {...register('descripcion')} error={errors.descripcion?.message} />

        <Controller name="fecha_resultado" control={control} render={({ field }) => (
          <DatePickerInput label="Fecha del resultado" description="La que puso el paciente; corríjala con la del archivo"
            valueFormat="DD/MM/YYYY" maxDate={new Date()} withAsterisk {...contained}
            value={field.value || null} onChange={(v) => field.onChange(v ?? '')}
            error={errors.fecha_resultado?.message} />
        )} />
      </Stack>
    </FormModal>
  )
}
