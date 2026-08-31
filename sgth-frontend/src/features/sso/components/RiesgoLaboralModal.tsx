'use client'

import { useEffect, useMemo } from 'react'
import {
  Modal, Button, Group, Stack,
  Select, Textarea, Switch, Alert, Text,
} from '@mantine/core'
import { useForm, useWatch, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconGauge } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useRiesgoLaboralMutations } from '../hooks/useRiesgosLaborales'
import { useFactoresRiesgo } from '../hooks/useFactoresRiesgo'
import {
  riesgoLaboralSchema, type RiesgoLaboralFormData,
  NIVEL_DEFICIENCIA_OPTIONS, NIVEL_EXPOSICION_OPTIONS, NIVEL_CONSECUENCIAS_OPTIONS,
  NIVEL_INTERVENCION_LABELS, NIVEL_INTERVENCION_COLORS, calcularNtp330,
} from '../schemas/riesgoLaboral.schema'
import type { RiesgoLaboral } from '../services/ssoService'

interface Props {
  opened:  boolean
  onClose: () => void
  riesgo?: RiesgoLaboral | null
}

export function RiesgoLaboralModal({ opened, onClose, riesgo }: Props) {
  const { isMobile }      = useMobileBreakpoint()
  const contained         = useContainedInput()
  const { crear, editar } = useRiesgoLaboralMutations()
  const { data: factores = [] } = useFactoresRiesgo()
  const isEditing         = !!riesgo

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RiesgoLaboralFormData>({
    resolver: zodResolver(riesgoLaboralSchema) as Resolver<RiesgoLaboralFormData>,
    defaultValues: {
      puesto_id:            riesgo?.puesto_id            ?? 0,
      factor_riesgo_id:     riesgo?.factor_riesgo_id      ?? 0,
      descripcion:          riesgo?.descripcion           ?? '',
      nivel_deficiencia:    (riesgo?.nivel_deficiencia as RiesgoLaboralFormData['nivel_deficiencia']) ?? 'mejorable',
      nivel_exposicion:     (riesgo?.nivel_exposicion as RiesgoLaboralFormData['nivel_exposicion']) ?? 'ocasional',
      nivel_consecuencias:  (riesgo?.nivel_consecuencias as RiesgoLaboralFormData['nivel_consecuencias']) ?? 'leve',
      medidas_preventivas:  riesgo?.medidas_preventivas   ?? '',
      estado:               riesgo?.estado                ?? true,
    },
  })

  useEffect(() => {
    reset({
      puesto_id:            riesgo?.puesto_id            ?? 0,
      factor_riesgo_id:     riesgo?.factor_riesgo_id      ?? 0,
      descripcion:          riesgo?.descripcion           ?? '',
      nivel_deficiencia:    (riesgo?.nivel_deficiencia as RiesgoLaboralFormData['nivel_deficiencia']) ?? 'mejorable',
      nivel_exposicion:     (riesgo?.nivel_exposicion as RiesgoLaboralFormData['nivel_exposicion']) ?? 'ocasional',
      nivel_consecuencias:  (riesgo?.nivel_consecuencias as RiesgoLaboralFormData['nivel_consecuencias']) ?? 'leve',
      medidas_preventivas:  riesgo?.medidas_preventivas   ?? '',
      estado:               riesgo?.estado                ?? true,
    })
  }, [riesgo, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: RiesgoLaboralFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: riesgo!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(handleClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  const nivelDeficiencia = useWatch({ control, name: 'nivel_deficiencia' })
  const nivelExposicion = useWatch({ control, name: 'nivel_exposicion' })
  const nivelConsecuencias = useWatch({ control, name: 'nivel_consecuencias' })

  const resultado = useMemo(
    () => calcularNtp330(nivelDeficiencia, nivelExposicion, nivelConsecuencias),
    [nivelDeficiencia, nivelExposicion, nivelConsecuencias],
  )

  const factorOptions = factores.map(f => ({ value: String(f.id), label: f.nombre }))

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar riesgo laboral' : 'Nuevo riesgo laboral'}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          <Controller
            name="puesto_id"
            control={control}
            render={({ field }) => (
              <BuscarPuestoSelect
                label="Puesto"
                required
                value={field.value || null}
                onChange={(id) => field.onChange(id ?? 0)}
                error={errors.puesto_id?.message}
              />
            )}
          />
          <Controller
            name="factor_riesgo_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Factor de riesgo"
                placeholder="Seleccione el factor identificado"
                data={factorOptions}
                searchable
                required
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : 0)}
                error={errors.factor_riesgo_id?.message}
              />
            )}
          />
          <Textarea
            label="Descripción"
            placeholder="Describa el riesgo identificado"
            rows={3}
            required
            {...contained}
            {...register('descripcion')}
            error={errors.descripcion?.message}
          />

          <Text size="sm" fw={500} mt="xs">Evaluación NTP 330 (INSHT)</Text>
          <Group grow>
            <Controller
              name="nivel_deficiencia"
              control={control}
              render={({ field }) => (
                <Select
                  label="Nivel de deficiencia"
                  data={NIVEL_DEFICIENCIA_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v as RiesgoLaboralFormData['nivel_deficiencia'])}
                  error={errors.nivel_deficiencia?.message}
                />
              )}
            />
            <Controller
              name="nivel_exposicion"
              control={control}
              render={({ field }) => (
                <Select
                  label="Nivel de exposición"
                  data={NIVEL_EXPOSICION_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v as RiesgoLaboralFormData['nivel_exposicion'])}
                  error={errors.nivel_exposicion?.message}
                />
              )}
            />
            <Controller
              name="nivel_consecuencias"
              control={control}
              render={({ field }) => (
                <Select
                  label="Nivel de consecuencias"
                  data={NIVEL_CONSECUENCIAS_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v as RiesgoLaboralFormData['nivel_consecuencias'])}
                  error={errors.nivel_consecuencias?.message}
                />
              )}
            />
          </Group>

          <Alert
            icon={<IconGauge size={18} />}
            color={NIVEL_INTERVENCION_COLORS[resultado.nivelIntervencion]}
            variant="light"
          >
            <Text size="sm">
              NP (probabilidad) = <b>{resultado.nivelProbabilidad}</b>{' '}
              &nbsp;·&nbsp; NR (riesgo) = <b>{resultado.nivelRiesgo}</b>
            </Text>
            <Text size="sm" fw={600}>
              {NIVEL_INTERVENCION_LABELS[resultado.nivelIntervencion]}
            </Text>
          </Alert>

          <Textarea
            label="Medidas preventivas"
            placeholder="Medidas recomendadas (opcional)"
            rows={2}
            {...contained}
            {...register('medidas_preventivas')}
            error={errors.medidas_preventivas?.message}
          />
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Switch
                label="Activo"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={isPending} color="emerald">
              {isEditing ? 'Actualizar' : 'Registrar riesgo'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
