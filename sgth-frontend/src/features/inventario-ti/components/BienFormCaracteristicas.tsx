'use client'

import { Grid, Select, TextInput, Textarea } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { BienFormData } from '../schemas/bien.schema'

interface Props {
  form: UseFormReturn<BienFormData>
}

const TIPOS_DISCO = [
  { value: 'SSD', label: 'SSD' },
  { value: 'HDD', label: 'HDD' },
]

/** Cómo es: sus datos en la red, lo principal del hardware y las observaciones. */
export function BienFormCaracteristicas({ form }: Props) {
  const contained = useContainedInput()
  const { register, control, formState: { errors } } = form

  return (
    <SectionCard title="Red y características">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Nombre en la red" placeholder="Ej.: LEG-GMERO01"
            error={errors.nombre_red?.message} {...contained} {...register('nombre_red')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Usuario del dominio" placeholder="Ej.: gmero"
            error={errors.usuario_dominio?.message} {...contained} {...register('usuario_dominio')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Dirección IP" placeholder="Ej.: 192.168.1.20"
            error={errors.direccion_ip?.message} {...contained} {...register('direccion_ip')} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Procesador" placeholder="Ej.: Core i7"
            error={errors.procesador?.message} {...contained} {...register('procesador')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Memoria RAM" placeholder="Ej.: 16 GB"
            error={errors.memoria_ram?.message} {...contained} {...register('memoria_ram')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Sistema operativo" placeholder="Ej.: Windows 11 Pro"
            error={errors.sistema_operativo?.message} {...contained} {...register('sistema_operativo')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <Controller name="tipo_disco" control={control} render={({ field }) => (
            <Select label="Tipo de disco" placeholder="SSD o HDD" data={TIPOS_DISCO} clearable
              {...contained} value={field.value || null} onChange={(v) => field.onChange(v ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput label="Capacidad del disco" placeholder="Ej.: 512 GB"
            error={errors.capacidad_disco?.message} {...contained} {...register('capacidad_disco')} />
        </Grid.Col>

        <Grid.Col span={12}>
          <Textarea label="Observaciones" placeholder="Opcional. Ej.: Incluye cargador; la batería no retiene carga."
            autosize minRows={2} maxRows={6}
            error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
        </Grid.Col>
      </Grid>
    </SectionCard>
  )
}
