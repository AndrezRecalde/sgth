'use client'

import { Alert, Grid, Select, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { MOTIVOS_BAJA } from '../constants/bajas'
import { useBajaMutations } from '../hooks/useBajas'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { proponerBajaSchema, type ProponerBajaFormData } from '../schemas/baja.schema'
import type { BienInformatico } from '../services/tipos'

interface Props {
  bien: BienInformatico | null
  onClose: () => void
}

/**
 * El informe técnico que propone la baja. El equipo sigue en servicio hasta
 * que el jefe de TIC la apruebe.
 */
export function ProponerBajaModal({ bien, onClose }: Props) {
  return bien ? <Formulario bien={bien} onClose={onClose} /> : null
}

function Formulario({ bien, onClose }: { bien: BienInformatico; onClose: () => void }) {
  const contained = useContainedInput()
  const { proponer } = useBajaMutations()
  const { data: catalogos } = useCatalogosFormulario()

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ProponerBajaFormData>({
    resolver: zodResolver(proponerBajaSchema),
    defaultValues: { motivo: 'dano_irreparable', diagnostico: '', recomendacion: '', fecha_informe: hoyIso(), soporte_anterior: '' },
  })

  const enviar = (v: ProponerBajaFormData) =>
    proponer.mutateAsync({
      ...v,
      bien_informatico_id: bien.id,
      recomendacion: v.recomendacion.trim() || null,
      soporte_anterior: v.soporte_anterior.trim() || null,
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(proponerBajaSchema.shape), null))

  return (
    <FormModal opened onClose={onClose} title="Proponer la baja" onSubmit={handleSubmit(enviar)}
      submitLabel="Proponer la baja" submitting={proponer.isPending} size="lg">
      <Stack gap="md">
        <Text size="sm">Equipo <b>{bien.codigo_institucional}</b> · {[bien.tipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' ')}</Text>
        <Alert color={SEMANTIC_COLOR.info} variant="light">
          El equipo sigue en servicio hasta que el jefe de TIC apruebe la baja. Al aprobarla se registra la
          devolución de su custodia{bien.componentes_count > 0 ? ' y se decide qué componentes salen con él' : ''}.
        </Alert>
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="motivo" control={control} render={({ field }) => (
              <Select label="Motivo" data={MOTIVOS_BAJA} allowDeselect={false} withAsterisk error={errors.motivo?.message}
                {...contained} value={field.value} onChange={(v) => v && field.onChange(v)} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="fecha_informe" control={control} render={({ field }) => (
              <DatePickerInput label="Fecha del informe" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
                error={errors.fecha_informe?.message} {...contained}
                value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
            )} />
          </Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Diagnóstico técnico" withAsterisk autosize minRows={3}
              placeholder="Ej.: La placa madre no enciende tras una descarga eléctrica; el repuesto ya no se fabrica."
              error={errors.diagnostico?.message} {...contained} {...register('diagnostico')} />
          </Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Recomendación" autosize minRows={2} placeholder="Ej.: Dar de baja y reponer el equipo."
              error={errors.recomendacion?.message} {...contained} {...register('recomendacion')} />
          </Grid.Col>
          {catalogos?.acepta_soporte_anterior && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput label="Soporte (sistema anterior)" placeholder="Ej.: 48990"
                error={errors.soporte_anterior?.message} {...contained} {...register('soporte_anterior')} />
            </Grid.Col>
          )}
        </Grid>
      </Stack>
    </FormModal>
  )
}
