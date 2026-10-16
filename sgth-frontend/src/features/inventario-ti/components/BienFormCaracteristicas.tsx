'use client'

import { Grid, Select, Text, TextInput, Textarea } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { CAMPOS_CARACTERISTICA, CAMPOS_POR_FICHA, ETIQUETA_FICHA, type CampoRed } from '../constants/fichas'
import type { BienFormData } from '../schemas/bien.schema'
import type { FichaTipo } from '../services/tipos'

interface Props {
  form: UseFormReturn<BienFormData>
  /** La del tipo elegido; sin tipo, `general`. */
  ficha: FichaTipo
}

const RED: Record<CampoRed, { etiqueta: string; placeholder: string }> = {
  nombre_red: { etiqueta: 'Nombre en la red', placeholder: 'Ej.: LEG-GMERO01' },
  usuario_dominio: { etiqueta: 'Usuario del dominio', placeholder: 'Ej.: gmero' },
  direccion_ip: { etiqueta: 'Dirección IP', placeholder: 'Ej.: 192.168.1.20' },
}

const COLUMNA = { base: 12, sm: 6, lg: 4 }

/**
 * Cómo es el equipo: los datos de red y las características que pide la
 * ficha de su tipo (un computador, su procesador; una impresora, su
 * conexión), y las observaciones. El consumible de la impresora ya no se
 * pide (5.4): lo anotado antes se muestra y se conserva.
 */
export function BienFormCaracteristicas({ form, ficha }: Props) {
  const contained = useContainedInput()
  const { register, control, getValues, formState: { errors } } = form
  const campos = CAMPOS_POR_FICHA[ficha]
  const consumibleAnterior = ficha === 'impresora' ? getValues('consumible').trim() : ''
  const vacia = campos.red.length === 0 && campos.caracteristicas.length === 0

  return (
    <SectionCard
      title="Red y características"
      description={ficha === 'general' ? undefined : `Las de un equipo de tipo ${ETIQUETA_FICHA[ficha].toLowerCase()}.`}
    >
      <Grid>
        {campos.red.map((campo) => (
          <Grid.Col key={campo} span={COLUMNA}>
            <TextInput label={RED[campo].etiqueta} placeholder={RED[campo].placeholder}
              error={errors[campo]?.message} {...contained} {...register(campo)} />
          </Grid.Col>
        ))}

        {campos.caracteristicas.map((clave) => {
          const c = CAMPOS_CARACTERISTICA[clave]
          return (
            <Grid.Col key={clave} span={COLUMNA}>
              {c.opciones ? (
                <Controller name={clave} control={control} render={({ field }) => (
                  <Select label={c.etiqueta} placeholder={c.placeholder} data={c.opciones} clearable
                    error={errors[clave]?.message} {...contained} value={field.value || null} onChange={(v) => field.onChange(v ?? '')} />
                )} />
              ) : (
                <TextInput label={c.etiqueta} placeholder={c.placeholder}
                  error={errors[clave]?.message} {...contained} {...register(clave)} />
              )}
            </Grid.Col>
          )
        })}

        {consumibleAnterior && (
          <Grid.Col span={12}>
            <Text size="sm" c="dimmed">
              Consumible anotado antes: <b>{consumibleAnterior}</b>. Ahora los consumibles van en su catálogo y en la pestaña Consumibles de la ficha.
            </Text>
          </Grid.Col>
        )}

        {vacia && (
          <Grid.Col span={12}>
            <Text size="sm" c="dimmed">Este tipo de equipo no tiene datos de red ni características propias.</Text>
          </Grid.Col>
        )}

        <Grid.Col span={12}>
          <Textarea label="Observaciones" placeholder="Opcional. Ej.: Incluye cargador; la batería no retiene carga."
            autosize minRows={2} maxRows={6}
            error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
        </Grid.Col>
      </Grid>
    </SectionCard>
  )
}
