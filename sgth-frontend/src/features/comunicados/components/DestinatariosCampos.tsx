'use client'

import { Alert, Group, MultiSelect, Radio, Stack, Text } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconAlertTriangle, IconUsers } from '@tabler/icons-react'
import { Controller, useWatch, type Control } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { plural } from '@/lib/plural'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { OPCIONES_REGIMEN } from '../constants/comunicados'
import { useConteoDestinatarios } from '../hooks/useComunicados'
import type { ComunicadoFormData } from '../schemas/comunicado.schema'

interface Props {
  control: Control<ComunicadoFormData>
}

/**
 * Se elige entre las direcciones (nivel 2 del orgánico), y cada una va con sus
 * jefaturas: el nivel 1 es la institución entera, que ya es «a todos», y
 * ofrecer las 59 unidades hacía la lista larga sin necesidad (2026-10-18).
 */
const NIVEL_DIRECCION = 2

const REGIMENES = ['losep', 'codigo_trabajo', 'servicios_profesionales'] as const
const esRegimen = (v: string): v is (typeof REGIMENES)[number] => REGIMENES.some((r) => r === v)

/**
 * A quién va (docs/diseno-noticias-comunicados.md, 3.3): todos, unas
 * direcciones o un régimen; dirección y régimen a la vez se cumplen los dos.
 * Debajo, cuántos lo verían hoy.
 */
export function DestinatariosCampos({ control }: Props) {
  const contained = useContainedInput()
  const { data: direcciones = [] } = useTodasUnidades({ nivel: NIVEL_DIRECCION })
  const [audiencia, elegidas, regimenes] = useWatch({ control, name: ['audiencia', 'unidades', 'regimenes'] })
  const [seleccion] = useDebouncedValue({ audiencia, unidades: elegidas, regimenes }, 400)
  const { data: total, isFetching } = useConteoDestinatarios(seleccion)

  const sinElegir = audiencia === 'segmentada' && !elegidas.length && !regimenes.length

  return (
    <Stack gap="md">
      <Controller
        name="audiencia"
        control={control}
        render={({ field }) => (
          <Radio.Group label="¿A quién va?" value={field.value} onChange={field.onChange}>
            <Group gap="xl" mt="xs">
              <Radio value="todos" label="A todos los servidores" />
              <Radio value="segmentada" label="A unas direcciones o a un régimen" />
            </Group>
          </Radio.Group>
        )}
      />

      {audiencia === 'segmentada' && (
        <>
          <Controller
            name="unidades"
            control={control}
            render={({ field }) => (
              <MultiSelect
                label="Direcciones"
                description="Cada una incluye las unidades que dependen de ella."
                placeholder="Busque una dirección"
                data={direcciones.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
                searchable
                clearable
                {...contained}
                value={field.value.map((u) => String(u.id))}
                onChange={(ids) => field.onChange(ids.map((id) => ({ id: Number(id), incluye_dependencias: true })))}
              />
            )}
          />

          <Controller
            name="regimenes"
            control={control}
            render={({ field }) => (
              <MultiSelect
                label="Régimen laboral"
                description="Vacío: cualquier régimen. Con direcciones, deben cumplirse las dos cosas."
                placeholder="Cualquiera"
                data={OPCIONES_REGIMEN}
                clearable
                {...contained}
                value={field.value}
                onChange={(valores) => field.onChange(valores.filter(esRegimen))}
              />
            )}
          />
        </>
      )}

      {sinElegir ? (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
          Elija al menos una dirección o un régimen, o envíelo a todos.
        </Alert>
      ) : (
        <Alert variant="light" color={total === 0 ? 'amber' : 'ocean'} icon={<IconUsers size={16} />}>
          <Text size="sm">
            {total === undefined || isFetching
              ? 'Contando a quién llegaría…'
              : total === 0
                ? 'Nadie lo recibiría: ningún servidor activo cumple lo elegido.'
                : `Hoy lo ${total === 1 ? 'vería' : 'verían'} ${plural(total, 'persona', 'personas')}.`}
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
