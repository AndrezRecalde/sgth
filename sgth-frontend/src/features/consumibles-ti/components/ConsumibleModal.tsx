'use client'

import { Grid, NumberInput, Select, Switch, TextInput } from '@mantine/core'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { COLORES_CONSUMIBLE, PRESENTACIONES, TIPOS_CONSUMIBLE } from '../constants/consumibles'
import { useConsumibleMutations } from '../hooks/useConsumibles'
import { consumibleSchema, type ConsumibleFormData } from '../schemas/consumible.schema'
import type { DetalleConsumible } from '../services/consumiblesService'
import { valoresDeConsumible } from '../utils/consumibleFormulario'
import { ModelosCompatiblesEditor } from './ModelosCompatiblesEditor'

const CAMPOS = Object.keys(consumibleSchema.shape)

interface Props {
  opened: boolean
  /** El consumible que se edita; sin él, se registra uno. */
  consumible?: DetalleConsumible
  onClose: () => void
  onCreado?: (id: number) => void
}

/**
 * Registrar o editar un consumible del catálogo, con los modelos de equipo a
 * los que sirve [P1]. Si lo despacha Bodega no lleva saldo en TIC, así que
 * tampoco stock mínimo [P4].
 */
export function ConsumibleModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ consumible, onClose, onCreado }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const editando = !!consumible
  const { crear, actualizar } = useConsumibleMutations(consumible?.id)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ConsumibleFormData>({
    resolver: zodResolver(consumibleSchema),
    defaultValues: valoresDeConsumible(consumible),
  })
  const modelos = useFieldArray({ control, name: 'modelos' })
  const guardaTic = useWatch({ control, name: 'guarda_tic' })
  const tipo = useWatch({ control, name: 'tipo' })

  const enviar = (v: ConsumibleFormData) => {
    const data = {
      ...v,
      codigo_bodega: v.codigo_bodega.trim() || null,
      stock_minimo: v.guarda_tic ? v.stock_minimo : 0,
      ...(editando ? {} : { activo: undefined }),
    }

    return (editando
      ? actualizar.mutateAsync(data).then(onClose)
      : crear.mutateAsync(data).then((c) => { onClose(); if (c) onCreado?.(c.id) }))
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))
  }

  return (
    <FormModal opened onClose={onClose} title={editando ? 'Editar consumible' : 'Nuevo consumible'}
      onSubmit={handleSubmit(enviar)} submitLabel={editando ? 'Guardar' : 'Registrar consumible'}
      submitting={crear.isPending || actualizar.isPending} size="lg">
      <Grid>
        <Grid.Col span={12}>
          <TextInput label="Descripción" placeholder="Ej.: Tóner HP CF400A negro" withAsterisk
            error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="tipo" control={control} render={({ field }) => (
            <Select label="Tipo" placeholder="Seleccionar" withAsterisk data={TIPOS_CONSUMIBLE} {...contained}
              error={errors.tipo?.message} value={field.value ?? null} onChange={(v) => field.onChange(v ?? undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="color" control={control} render={({ field }) => (
            <Select label="Color" placeholder="No aplica" clearable data={COLORES_CONSUMIBLE} {...contained}
              error={errors.color?.message} value={field.value} onChange={(v) => field.onChange(v ?? null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="presentacion" control={control} render={({ field }) => (
            <Select label="Presentación" withAsterisk data={PRESENTACIONES} allowDeselect={false} {...contained}
              error={errors.presentacion?.message} value={field.value} onChange={(v) => field.onChange(v ?? undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput label="Código de bodega" placeholder="Opcional. Ej.: 0008436"
            error={errors.codigo_bodega?.message} {...contained} {...register('codigo_bodega')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: guardaTic ? 8 : 12 }}>
          <Controller name="guarda_tic" control={control} render={({ field }) => (
            <Switch label="Lo guarda TIC" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
              description={field.value
                ? 'TIC lo tiene en su bodega: lleva apertura, ingresos, entregas, ajustes y saldo. Bajo el mínimo, se avisa en el tablero.'
                : 'Lo despacha Bodega: solo se registran las entregas, sin saldo en TIC.'} />
          )} />
        </Grid.Col>
        {guardaTic && (
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller name="stock_minimo" control={control} render={({ field }) => (
              <NumberInput label="Stock mínimo" placeholder="0 = sin aviso" min={0} max={9999} allowDecimal={false} hideControls
                error={errors.stock_minimo?.message} {...contained}
                value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)} />
            )} />
          </Grid.Col>
        )}
        {editando && (
          <Grid.Col span={12}>
            <Controller name="activo" control={control} render={({ field }) => (
              <Switch label="Activo" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
                description="Lo que ya no se compra se desactiva: deja de ofrecerse, pero conserva su kárdex." />
            )} />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <ModelosCompatiblesEditor
            modelos={modelos.fields}
            onAgregar={(m) => modelos.append(m)}
            onQuitar={(i) => modelos.remove(i)}
            error={errors.modelos?.message}
            opcional={tipo === 'repuesto'}
          />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
