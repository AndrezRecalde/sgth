'use client'

import { useMemo } from 'react'
import { Alert, Group, NumberInput, Paper, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useDebouncedValue } from '@mantine/hooks'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { dinero } from '@/features/expediente/utils/dinero'
import { useGruposOcupacionales, type GrupoOcupacional } from '../hooks/useGruposOcupacionales'
import { useRegistrarEscala, useVistaPreviaEscala } from '../hooks/useEscalaRemuneracion'
import { escalaRemuneracionSchema, type EscalaRemuneracionFormData } from '../schemas/escalaRemuneracion.schema'

interface Props {
  opened: boolean
  onClose: () => void
}

/**
 * La nueva escala de remuneraciones de la LOSEP (fase 7.3b): la resolución que
 * fija la RMU nueva de los grados, desde una fecha —hoy o antes—. Sube el grado
 * en el catálogo y la remuneración de cada vínculo, sin acción de personal: cada
 * uno deja su constancia en la bitácora, y la nómina recibe sus novedades.
 */
export function NuevaEscalaModal({ opened, onClose }: Props) {
  const { data: grupos } = useGruposOcupacionales()

  // El formulario nace con un renglón por grado: se monta cuando llegan.
  return opened && grupos ? <Formulario grados={grupos.filter((g) => g.admite_escala)} onClose={onClose} /> : null
}

function Formulario({ grados, onClose }: { grados: GrupoOcupacional[]; onClose: () => void }) {
  const contained = useContainedInput()
  const { hasPermiso } = useAuth()
  const puedeRegistrar = hasPermiso('registrar-accion-personal')
  const registrar = useRegistrarEscala()

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<EscalaRemuneracionFormData>({
    resolver: zodResolver(escalaRemuneracionSchema),
    defaultValues: {
      instrumento: '',
      fecha_instrumento: hoyIso(),
      rige_desde: hoyIso(),
      dictamen_presupuestario_ref: '',
      descripcion: '',
      grados: grados.map((g) => ({ grupo_ocupacional_id: g.id, rmu_nueva: null })),
    },
  })
  const renglones = useWatch({ control, name: 'grados' }) ?? []

  const pedidos = renglones
    .filter((r) => r.rmu_nueva != null && r.rmu_nueva > 0)
    .map((r) => ({ grupo_ocupacional_id: r.grupo_ocupacional_id, rmu_nueva: r.rmu_nueva as number }))
  // Se pide cuando se deja de escribir, no con cada tecla. Por su texto: el
  // arreglo es otro en cada render, y la espera no terminaría nunca.
  const [claveQuieta] = useDebouncedValue(JSON.stringify(pedidos), 400)
  const pedidosQuietos = useMemo(() => JSON.parse(claveQuieta) as typeof pedidos, [claveQuieta])
  const previa = useVistaPreviaEscala(pedidosQuietos)
  const impedimentos = previa.data?.impedimentos ?? []
  const suben = (previa.data?.vinculos ?? []).filter((v) => v.sube)
  const quedan = (previa.data?.vinculos ?? []).filter((v) => !v.sube)

  const enviar = async (v: EscalaRemuneracionFormData) => {
    try {
      await registrar.mutateAsync({
        ...v,
        descripcion: v.descripcion?.trim() || null,
        grados: pedidos,
      })
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in escalaRemuneracionSchema.shape) setError(campo as keyof EscalaRemuneracionFormData, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Nueva escala de remuneraciones"
      size="xl"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar la escala"
      submitting={registrar.isPending}
      submitDisabled={!puedeRegistrar || !pedidos.length || impedimentos.length > 0 || previa.isLoading || !!previa.error}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Sube la RMU de los grados en el catálogo y la de cada servidor que los ocupa, sin acción de personal: cada uno
          queda anotado en la bitácora de su vínculo y la nómina recibe sus novedades. Nadie baja: quien ya cobra la
          RMU nueva o más se queda como está. Las autoridades electas y los obreros no van: lo suyo tiene su propio camino.
          {!puedeRegistrar && ' La registra el Director de Talento Humano: aquí puede revisar a quién sube.'}
        </Alert>

        <Paper withBorder p="sm" radius="md">
          <Stack gap={6}>
            <Text size="sm" fw={600}>RMU nueva de cada grado</Text>
            <Text size="xs" c="dimmed">Deje en blanco los grados que no cambian.</Text>
            {grados.map((g, i) => (
              <Group key={g.id} justify="space-between" wrap="nowrap" gap="sm">
                <Text size="sm" style={{ flex: 1, minWidth: 0 }}>
                  <b>{g.grado_codigo}</b> · {g.denominacion_generica ?? g.grupo} · hoy {dinero(g.rmu)}
                </Text>
                <Controller
                  name={`grados.${i}.rmu_nueva`}
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      aria-label={`RMU nueva del grado ${g.grado_codigo}`}
                      placeholder="Igual"
                      min={0}
                      decimalScale={2}
                      hideControls
                      prefix="$ "
                      value={field.value ?? ''}
                      onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
                      w={140}
                      {...contained}
                    />
                  )}
                />
              </Group>
            ))}
            {errors.grados?.root?.message && <Text size="xs" c="red">{errors.grados.root.message}</Text>}
            {errors.grados?.message && <Text size="xs" c="red">{errors.grados.message}</Text>}
          </Stack>
        </Paper>

        {previa.data && pedidos.length > 0 && (
          <Stack gap={4}>
            <Text size="sm" fw={600}>
              {suben.length === 1 ? 'Sube 1 servidor' : `Suben ${suben.length} servidores`}
              {quedan.length > 0 && ` · ${quedan.length} ya cobra(n) la RMU nueva o más`}
            </Text>
            {suben.map((v) => (
              <Text key={v.contrato_id} size="xs">
                {v.servidor} · {v.grado} · {dinero(v.remuneracion_anterior)} → <b>{dinero(v.remuneracion_nueva)}</b>
              </Text>
            ))}
          </Stack>
        )}

        {impedimentos.length > 0 && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
            <Stack gap={2}>
              {impedimentos.map((i) => <Text key={i} size="sm">{i}</Text>)}
            </Stack>
          </Alert>
        )}

        <Group grow align="flex-start">
          <TextInput
            label="Resolución que fija la escala"
            withAsterisk
            error={errors.instrumento?.message}
            {...contained}
            {...register('instrumento')}
          />
          <Controller
            name="fecha_instrumento"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de la resolución"
                withAsterisk
                valueFormat="DD/MM/YYYY"
                maxDate={hoyIso()}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
                error={errors.fecha_instrumento?.message}
                {...contained}
              />
            )}
          />
        </Group>
        <Group grow align="flex-start">
          <Controller
            name="rige_desde"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Rige desde"
                description="Hoy o antes. Si es antes, la nómina recibe la diferencia de cada mes."
                withAsterisk
                valueFormat="DD/MM/YYYY"
                maxDate={hoyIso()}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
                error={errors.rige_desde?.message}
                {...contained}
              />
            )}
          />
          <TextInput
            label="Dictamen presupuestario"
            description="Sube el gasto en personal: es obligatorio."
            withAsterisk
            error={errors.dictamen_presupuestario_ref?.message}
            {...contained}
            {...register('dictamen_presupuestario_ref')}
          />
        </Group>
        <Textarea
          label="Explicación"
          autosize
          minRows={2}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
