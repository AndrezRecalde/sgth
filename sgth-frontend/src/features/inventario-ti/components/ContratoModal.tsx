'use client'

import { Button, Grid, Stack, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconPlus } from '@tabler/icons-react'
import { Controller, useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useCatalogoMutations } from '../hooks/useCatalogosInventario'
import { contratoSchema, type ContratoFormData } from '../schemas/contrato.schema'
import { catalogosService } from '../services/catalogosService'
import type { ContratoVigencia } from '../services/tipos'
import { columnasCronograma } from './cronograma.columns'

const CAMPOS = ['proceso', 'proveedor', 'objeto', 'administrador_id', 'fecha_inicio', 'fecha_fin', 'observaciones', 'cronograma']

interface Props {
  opened: boolean
  onClose: () => void
  contrato: ContratoVigencia | null
}

/** El contrato de vigencia tecnológica de un proceso de compra, con su cronograma. */
export function ContratoModal({ opened, onClose, contrato }: Props) {
  return opened ? <Formulario onClose={onClose} contrato={contrato} /> : null
}

function Formulario({ onClose, contrato }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { guardar } = useCatalogoMutations('contrato', catalogosService.contrato)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ContratoFormData>({
    resolver: zodResolver(contratoSchema),
    defaultValues: {
      proceso: contrato?.proceso ?? '',
      proveedor: contrato?.proveedor ?? '',
      objeto: contrato?.objeto ?? '',
      administrador_id: contrato?.administrador_id ?? null,
      fecha_inicio: contrato?.fecha_inicio ?? '',
      fecha_fin: contrato?.fecha_fin ?? '',
      observaciones: contrato?.observaciones ?? '',
      cronograma: (contrato?.cronograma ?? []).map((c) => ({ fecha_desde: c.fecha_desde, fecha_hasta: c.fecha_hasta, descripcion: c.descripcion ?? '' })),
    },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'cronograma' })

  const nulo = (v: string) => (v.trim() === '' ? null : v.trim())
  const enviar = (v: ContratoFormData) =>
    guardar.mutateAsync({
      id: contrato?.id,
      data: {
        proceso: v.proceso, proveedor: nulo(v.proveedor), objeto: nulo(v.objeto),
        administrador_id: v.administrador_id, fecha_inicio: nulo(v.fecha_inicio), fecha_fin: nulo(v.fecha_fin),
        observaciones: nulo(v.observaciones),
        cronograma: v.cronograma.map((c) => ({ ...c, descripcion: nulo(c.descripcion) })),
      },
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar el contrato'))

  const fecha = (campo: 'fecha_inicio' | 'fecha_fin', label: string) => (
    <Controller name={campo} control={control} render={({ field }) => (
      <DatePickerInput label={label} placeholder="Seleccionar fecha" valueFormat="DD/MM/YYYY" clearable
        error={errors[campo]?.message} {...contained}
        value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
    )} />
  )

  return (
    <FormModal
      opened
      onClose={onClose}
      title={contrato ? 'Editar contrato de vigencia' : 'Nuevo contrato de vigencia'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={contrato ? 'Guardar cambios' : 'Registrar contrato'}
      submitting={guardar.isPending}
      closeOnClickOutside={false}
      size="xl"
    >
      <Stack gap="md">
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput label="Proceso de compra" placeholder="Ej.: SIE-GADPE-018-2021" withAsterisk
              error={errors.proceso?.message} {...contained} {...register('proceso')} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput label="Proveedor" placeholder="Razón social"
              error={errors.proveedor?.message} {...contained} {...register('proveedor')} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="administrador_id" control={control} render={({ field }) => (
              <BuscarServidorSelect label="Administrador del contrato" value={field.value}
                onChange={field.onChange} error={errors.administrador_id?.message} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 6, sm: 3 }}>{fecha('fecha_inicio', 'Inicio')}</Grid.Col>
          <Grid.Col span={{ base: 6, sm: 3 }}>{fecha('fecha_fin', 'Fin')}</Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Objeto" placeholder="Opcional. Qué cubre el contrato" autosize minRows={2}
              error={errors.objeto?.message} {...contained} {...register('objeto')} />
          </Grid.Col>
        </Grid>

        <SectionHeading
          title="Cronograma de mantenimientos"
          action={<Button variant="subtle" size="xs" leftSection={<IconPlus size={14} />}
            onClick={() => append({ fecha_desde: '', fecha_hasta: '', descripcion: '' })}>Agregar ventana</Button>}
        />
        <SgthTable
          records={fields}
          columns={columnasCronograma({ control, errors, contained, quitar: remove })}
          minHeight={100}
          noRecordsText="Sin ventanas de mantenimiento. Agregue las que fija el contrato."
        />
      </Stack>
    </FormModal>
  )
}
