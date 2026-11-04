'use client'

import { useEffect, useMemo } from 'react'
import { Alert, Select, Stack, Text } from '@mantine/core'
import { Controller, useForm, useWatch, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { NivelCompetencia, PuestoCompetencia, TipoCompetencia } from '@/types/api'
import { NIVELES_COMPETENCIA, TIPOS_COMPETENCIA } from '../constants/perfilPuesto'
import { useCompetencias, usePuestoCompetenciaMutations } from '../hooks/useCompetencias'
import { puestoCompetenciaSchema, type PuestoCompetenciaFormData } from '../schemas/perfilPuesto.schema'

interface Props {
  opened:        boolean
  onClose:       () => void
  puestoId:      number
  tipo:          TipoCompetencia
  /** El nivel con que arranca una nueva: el de la complejidad del puesto. */
  nivelInicial:  NivelCompetencia
  /** Las que el puesto ya exige: no se ofrecen otra vez. */
  yaAsignadas:   number[]
  /** Con una asignada, solo se cambia su nivel. */
  asignada?:     PuestoCompetencia | null
}

/**
 * Agregar una competencia del diccionario al puesto, o cambiar su nivel. Se
 * muestra el comportamiento observable del nivel elegido y su número en el
 * FOR-02, que es lo que el jefe copia.
 */
export function PuestoCompetenciaModal({
  opened, onClose, puestoId, tipo, nivelInicial, yaAsignadas, asignada,
}: Props) {
  const contained = useContainedInput()
  const { agregar, cambiarNivel } = usePuestoCompetenciaMutations(puestoId)
  // Al cambiar el nivel de una ya asignada, el diccionario entero: si se
  // retiró después, no estaba entre las vigentes, y el recuadro mostraba el
  // comportamiento del nivel guardado en vez del elegido.
  const { data: diccionario = [], isLoading, isError } = useCompetencias({ tipo, solo_activas: !asignada }, opened)
  const etiquetaTipo = TIPOS_COMPETENCIA.find(t => t.value === tipo)?.label.toLowerCase()

  const iniciales: DefaultValues<PuestoCompetenciaFormData> = asignada
    ? { competencia_id: asignada.competencia_id, nivel: asignada.nivel }
    : { nivel: nivelInicial }     // la competencia se omite: la elige quien abre

  const { control, handleSubmit, reset, setError, formState: { errors } } =
    useForm<PuestoCompetenciaFormData>({
      resolver: zodResolver(puestoCompetenciaSchema),
      defaultValues: iniciales,
    })

  useEffect(() => {
    if (opened) reset(asignada ? { competencia_id: asignada.competencia_id, nivel: asignada.nivel } : { nivel: nivelInicial })
  }, [opened, asignada, nivelInicial, reset])

  const competenciaId = useWatch({ control, name: 'competencia_id' })
  const nivel = useWatch({ control, name: 'nivel' })
  const elegida = diccionario.find(c => c.id === competenciaId)

  const opciones = useMemo(
    () => diccionario
      .filter(c => !yaAsignadas.includes(c.id))
      .map(c => ({ value: String(c.id), label: `${c.numero}. ${c.nombre}` })),
    [diccionario, yaAsignadas],
  )

  const guardar = async (v: PuestoCompetenciaFormData) => {
    try {
      if (asignada) await cambiarNivel.mutateAsync({ id: asignada.id, nivel: v.nivel })
      else await agregar.mutateAsync(v)
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in puestoCompetenciaSchema.shape) {
          setError(campo as keyof PuestoCompetenciaFormData, { type: 'server', message: mensaje })
        }
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={asignada ? 'Cambiar el nivel' : `Agregar competencia ${etiquetaTipo}`}
      size="lg"
      onSubmit={handleSubmit(guardar)}
      submitLabel={asignada ? 'Guardar nivel' : 'Agregar al puesto'}
      submitting={agregar.isPending || cambiarNivel.isPending}
    >
      <Stack gap="sm">
        {asignada ? (
          <Text size="sm" fw={500}>{asignada.nombre}</Text>
        ) : (
          <Controller
            name="competencia_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Competencia"
                placeholder={isLoading ? 'Cargando el diccionario…' : 'Busque por nombre o número'}
                data={opciones}
                searchable
                nothingFoundMessage={isError
                  ? 'No se pudo cargar el diccionario. Cierre y vuelva a intentarlo.'
                  : 'Ninguna competencia coincide, o el puesto ya las exige todas'}
                withAsterisk
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.competencia_id?.message}
              />
            )}
          />
        )}
        <Controller
          name="nivel"
          control={control}
          render={({ field }) => (
            <Select
              label="Nivel que exige el puesto"
              data={NIVELES_COMPETENCIA}
              allowDeselect={false}
              withAsterisk
              {...contained}
              value={field.value ?? null}
              onChange={(v) => field.onChange(NIVELES_COMPETENCIA.find(n => n.value === v)?.value)}
              error={errors.nivel?.message}
            />
          )}
        />

        {(elegida || asignada) && nivel && (
          <Alert variant="light" color="slate" title="Comportamiento observable">
            <Stack gap={6}>
              <Text size="sm">
                {elegida ? elegida[`comportamiento_${nivel}`] : asignada?.comportamiento}
              </Text>
              {elegida && (
                <Text size="xs" c="dimmed">
                  Es el comportamiento n.º {elegida.numeros_comportamiento[nivel]} del diccionario.
                </Text>
              )}
              {elegida?.definicion && (
                <Text size="xs" c="dimmed"><Text span fw={600}>Definición: </Text>{elegida.definicion}</Text>
              )}
            </Stack>
          </Alert>
        )}
      </Stack>
    </FormModal>
  )
}
