'use client'

import { Checkbox, Grid, Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useBajaMutations } from '../hooks/useBajas'
import { useBien } from '../hooks/useBienes'
import { aprobarBajaSchema, type AprobarBajaFormData } from '../schemas/baja.schema'
import type { BajaBien } from '../services/tiposBaja'

interface Props {
  baja: BajaBien
  onClose: () => void
}

/**
 * Aprobar la baja con el memorando de Quipux. Saca al equipo del servicio,
 * devuelve su custodia y decide qué componentes salen con él: los que no se
 * marcan se sueltan y siguen en servicio.
 */
export function AprobarBajaModal({ baja, onClose }: Props) {
  const contained = useContainedInput()
  const { aprobar } = useBajaMutations()
  const { data: bien } = useBien(baja.bien_informatico_id)
  const componentes = (bien?.componentes ?? []).filter((c) => c.estado_operativo !== 'dado_de_baja')

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<AprobarBajaFormData>({
    resolver: zodResolver(aprobarBajaSchema),
    defaultValues: { fecha_baja: hoyIso(), memorando_numero: '', componentes_baja: [] },
  })

  const enviar = (v: AprobarBajaFormData) =>
    aprobar.mutateAsync({ id: baja.id, data: v })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(aprobarBajaSchema.shape), null))

  return (
    <FormModal opened onClose={onClose} title={`Aprobar la baja ${baja.numero}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Aprobar la baja" submitting={aprobar.isPending} size="lg">
      <Stack gap="md">
        <Text size="sm">
          Equipo <b>{baja.bien?.codigo_institucional}</b> · {baja.motivo_etiqueta}.
          {bien?.custodio ? <> Se registrará la devolución de la custodia de <b>{bien.custodio.nombre}</b>.</> : null}
        </Text>
        <Grid>
          <Grid.Col span={{ base: 12, sm: 5 }}>
            <Controller name="fecha_baja" control={control} render={({ field }) => (
              <DatePickerInput label="Fecha de la baja" valueFormat="DD/MM/YYYY" withAsterisk
                minDate={baja.fecha_informe} maxDate={hoyIso()} error={errors.fecha_baja?.message} {...contained}
                value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 7 }}>
            <TextInput label="Memorando de Quipux" placeholder="Ej.: Memorando Nº 120 GADPE-GTIC-2026" withAsterisk
              error={errors.memorando_numero?.message} {...contained} {...register('memorando_numero')} />
          </Grid.Col>
        </Grid>
        {componentes.length > 0 && (
          <Controller name="componentes_baja" control={control} render={({ field }) => (
            <Checkbox.Group
              label="Componentes que salen con el equipo"
              description="Los que no marque se sueltan del equipo y siguen en servicio."
              error={errors.componentes_baja?.message}
              value={field.value.map(String)}
              onChange={(v) => field.onChange(v.map(Number))}
            >
              <Stack gap={6} mt="xs">
                {componentes.map((c) => (
                  <Checkbox key={c.id} value={String(c.id)}
                    label={`${c.codigo_institucional} · ${[c.tipo, c.modelo].filter(Boolean).join(' ')}`} />
                ))}
              </Stack>
            </Checkbox.Group>
          )} />
        )}
      </Stack>
    </FormModal>
  )
}
