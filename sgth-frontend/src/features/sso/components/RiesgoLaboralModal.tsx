'use client'

import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useEffect, useMemo } from 'react'
import {
  Group, Stack,
  Select, Textarea, Switch, Alert, Text,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, useWatch, Controller, type DefaultValues, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconGauge } from '@tabler/icons-react'
import type { ZodType } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useRiesgoLaboralMutations } from '../hooks/useRiesgosLaborales'
import { useFactoresRiesgo } from '../hooks/useFactoresRiesgo'
import {
  riesgoLaboralSchema, type RiesgoLaboralFormData,
  NIVEL_DEFICIENCIA_OPTIONS, NIVEL_EXPOSICION_OPTIONS, NIVEL_CONSECUENCIAS_OPTIONS,
  NIVEL_INTERVENCION_LABELS, TONO_NIVEL_INTERVENCION, calcularNtp330,
} from '../schemas/riesgoLaboral.schema'
import type { RiesgoLaboral } from '../services/ssoService'

interface Props {
  opened:  boolean
  onClose: () => void
  riesgo?: RiesgoLaboral | null
}

/**
 * El nivel que viene del API, solo si es uno de los que el esquema acepta.
 *
 * Se valida contra el propio esquema Zod en vez de afirmar el tipo con `as`:
 * estos campos llegan como `string | null` —son nullable en la base— y un
 * riesgo anterior a la matriz NTP 330 los trae en NULL.
 */
function nivelDelApi<T extends string>(
  esquema: ZodType<T>,
  valor: string | null | undefined,
): T | undefined {
  const resultado = esquema.safeParse(valor)
  return resultado.success ? resultado.data : undefined
}

/**
 * Un riesgo nuevo arranca en el centro de la escala; uno existente conserva su
 * valoración.
 *
 * Si no la tiene —identificado antes de NTP 330, con los tres niveles en
 * NULL— los selectores arrancan **vacíos** y hay que elegirlos: heredar el
 * centro de la escala le inventaría una valoración de un clic, y la valoración
 * de un riesgo laboral es justo lo que nadie debería poner por descuido. El
 * esquema los exige, así que el formulario no se envía sin ellos.
 */
function valoresIniciales(riesgo?: RiesgoLaboral | null): DefaultValues<RiesgoLaboralFormData> {
  const base: DefaultValues<RiesgoLaboralFormData> = {
    puesto_id:           riesgo?.puesto_id           ?? 0,
    factor_riesgo_id:    riesgo?.factor_riesgo_id    ?? 0,
    descripcion:         riesgo?.descripcion         ?? '',
    medidas_preventivas: riesgo?.medidas_preventivas ?? '',
    estado:              riesgo?.estado              ?? true,
  }

  if (!riesgo) {
    return {
      ...base,
      nivel_deficiencia:   'mejorable',
      nivel_exposicion:    'ocasional',
      nivel_consecuencias: 'leve',
    }
  }

  const forma = riesgoLaboralSchema.shape
  const deficiencia   = nivelDelApi(forma.nivel_deficiencia, riesgo.nivel_deficiencia)
  const exposicion    = nivelDelApi(forma.nivel_exposicion, riesgo.nivel_exposicion)
  const consecuencias = nivelDelApi(forma.nivel_consecuencias, riesgo.nivel_consecuencias)

  return {
    ...base,
    ...(deficiencia ? { nivel_deficiencia: deficiencia } : {}),
    ...(exposicion ? { nivel_exposicion: exposicion } : {}),
    ...(consecuencias ? { nivel_consecuencias: consecuencias } : {}),
  }
}

export function RiesgoLaboralModal({ opened, onClose, riesgo }: Props) {
  const contained         = useContainedInput()
  const { crear, editar } = useRiesgoLaboralMutations()
  const { data: factores = [], error: errorFactores } = useFactoresRiesgo()
  const isEditing         = !!riesgo

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RiesgoLaboralFormData>({
    resolver: zodResolver(riesgoLaboralSchema) as Resolver<RiesgoLaboralFormData>,
    defaultValues: valoresIniciales(riesgo),
  })

  useEffect(() => {
    reset(valoresIniciales(riesgo))
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

  // Un riesgo sin valorar llega con los tres vacíos: calcular sobre ellos daría
  // NR 0 y «No intervenir», que es la lectura más tranquilizadora posible y
  // justo la que no corresponde.
  const valoracionCompleta = !!nivelDeficiencia && !!nivelExposicion && !!nivelConsecuencias

  const resultado = useMemo(
    () => (valoracionCompleta
      ? calcularNtp330(nivelDeficiencia, nivelExposicion, nivelConsecuencias)
      : null),
    [valoracionCompleta, nivelDeficiencia, nivelExposicion, nivelConsecuencias],
  )

  const factorOptions = factores.map(f => ({ value: String(f.id), label: f.nombre }))

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar riesgo laboral' : 'Nuevo riesgo laboral'}
      size="lg"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={isEditing ? 'Actualizar' : 'Registrar riesgo'}
      submitting={isPending}
    >
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
              // Un catálogo que no cargó se veía como un catálogo vacío, y el
              // usuario concluía que no hay factores registrados.
              error={
                errors.factor_riesgo_id?.message
                ?? (errorFactores ? 'No se pudo cargar el catálogo de factores de riesgo.' : undefined)
              }
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
                placeholder="Seleccione"
                required
                data={NIVEL_DEFICIENCIA_OPTIONS}
                {...contained}
                value={field.value ?? null}
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
                placeholder="Seleccione"
                required
                data={NIVEL_EXPOSICION_OPTIONS}
                {...contained}
                value={field.value ?? null}
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
                placeholder="Seleccione"
                required
                data={NIVEL_CONSECUENCIAS_OPTIONS}
                {...contained}
                value={field.value ?? null}
                onChange={(v) => field.onChange(v as RiesgoLaboralFormData['nivel_consecuencias'])}
                error={errors.nivel_consecuencias?.message}
              />
            )}
          />
        </Group>

        {resultado ? (
          <Alert
            icon={<IconGauge size={18} />}
            color={SEMANTIC_COLOR[TONO_NIVEL_INTERVENCION[resultado.nivelIntervencion]]}
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
        ) : (
          <Alert icon={<IconGauge size={18} />} color="slate" variant="light">
            <Text size="sm">
              {isEditing
                ? 'Este riesgo se identificó antes de la matriz NTP 330 y no tiene valoración. Elija los tres niveles para calcularla.'
                : 'Elija los tres niveles para calcular la valoración.'}
            </Text>
          </Alert>
        )}

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
      </Stack>
    </FormModal>
  )
}
