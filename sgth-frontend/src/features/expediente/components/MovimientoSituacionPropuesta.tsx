'use client'

import { NumberInput, Select, Stack, TextInput } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { esLosep, remuneracionEsHeredada } from '../utils/nombramiento'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { BloqueDetalle } from './BloqueDetalle'
import { MovimientoDatosContratacion } from './MovimientoDatosContratacion'

interface Props {
  form: UseFormReturn<MovimientoFormData>
  /** El ingreso crea el vínculo, así que suma los datos de la contratación. */
  esIngreso: boolean
  /** Nombramiento del vínculo VIGENTE del servidor, si lo tiene. */
  tipoNombramiento?: string | null
}

/**
 * La columna derecha del documento: dónde queda el servidor después del acto.
 *
 * La piden el ingreso —que es donde nace el vínculo— y las acciones que
 * reubican: el traspaso y la prestación de servicios. Una cesación no propone
 * nada y una comisión deja al servidor en su puesto, así que ahí este bloque no
 * se monta.
 */
export function MovimientoSituacionPropuesta({ form, esIngreso, tipoNombramiento }: Props) {
  const contained = useContainedInput()
  const { control, register, setValue, formState: { errors } } = form

  const unidadDestinoId = useWatch({ control, name: 'unidad_destino_id' })
  const puestoDestinoId = useWatch({ control, name: 'puesto_destino_id' })
  const nombramiento = useWatch({ control, name: 'tipo_nombramiento_propuesto' })

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })

  const { data: puestosData } = usePuestos(
    unidadDestinoId ? { unidad_administrativa_id: Number(unidadDestinoId), per_page: 100 } : undefined,
  )
  const puestos = puestosData?.data ?? []
  const puestosTruncados = (puestosData?.total ?? 0) > puestos.length

  /**
   * Qué régimen decide si la R.M.U. se hereda o se teclea.
   *
   * En un ingreso es el nombramiento PROPUESTO: es el régimen que va a tener el
   * vínculo que nace, y quien ingresa no tiene ninguno vigente del que sacarlo.
   * Antes se usaba el del vínculo vigente también aquí, y como en un ingreso
   * llega vacío, `esLosep(undefined)` daba true: el campo salía en solo lectura
   * diciendo «No se edita en régimen LOSEP» en todo ingreso, incluidos los de
   * Código del Trabajo y Servicios Profesionales, que son justo los dos casos en
   * que la remuneración se negocia en el contrato.
   *
   * En un traspaso o una prestación de servicios sigue siendo el vigente: esas
   * acciones no cambian de nombramiento, reubican dentro del que ya tiene.
   */
  const regimenDeLaRmu = esIngreso ? nombramiento : tipoNombramiento

  const puestoDestino = puestos.find((p) => p.id === Number(puestoDestinoId))
  const rmuHeredada = remuneracionEsHeredada(
    regimenDeLaRmu,
    puestoDestino?.rmu != null ? Number(puestoDestino.rmu) : null,
  )

  const descripcionRmu = rmuHeredada
    ? 'Fijada por el grupo ocupacional del puesto destino. No se edita en régimen LOSEP.'
    : esIngreso && !nombramiento
      ? 'Elija primero el tipo de nombramiento: de él depende si la remuneración se hereda del puesto o se pacta.'
      : esLosep(regimenDeLaRmu)
        ? (puestoDestinoId
          ? 'Este puesto no tiene grupo ocupacional asignado, así que no hay monto que heredar: ingréselo a mano.'
          : 'Elija el puesto destino para heredar la remuneración de su grupo ocupacional.')
        : 'Se pacta en el contrato: este régimen no toma la remuneración del puesto.'

  return (
    <BloqueDetalle>
      <SectionHeading title="Situación propuesta" mb="xs" />
      <Stack gap="xs">
        <Controller
          name="unidad_destino_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Unidad administrativa"
              placeholder="Seleccionar"
              data={unidades.map((u) => ({
                value: String(u.id), label: u.nombre ?? `Unidad ${u.id}`,
              }))}
              searchable
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(v ? Number(v) : null)
                setValue('puesto_destino_id', null)
              }}
              error={errors.unidad_destino_id?.message}
              {...contained}
            />
          )}
        />

        <Controller
          name="puesto_destino_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Puesto"
              // El listado pide 100 por página. Si la unidad tuviera más, antes
              // se recortaba en silencio y el puesto que falta era
              // indistinguible de uno que no existe. El día que esto aparezca,
              // el arreglo de fondo es una búsqueda del lado del servidor: el
              // endpoint de puestos todavía no acepta `search`.
              description={puestosTruncados
                ? `Se muestran ${puestos.length} de ${puestosData?.total} puestos de la unidad.`
                : undefined}
              placeholder={unidadDestinoId ? 'Seleccionar' : 'Elija primero la unidad'}
              data={puestos.map((p) => ({
                value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}`,
              }))}
              searchable
              disabled={!unidadDestinoId}
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(v ? Number(v) : null)
                const sel = puestos.find((p) => p.id === Number(v))
                if (sel?.rmu) setValue('remuneracion_propuesta', Number(sel.rmu))
                // La partida del puesto es la sugerencia, no una imposición: el
                // campo queda editable porque Talento Humano puede respaldar el
                // vínculo con otra.
                if (sel?.partida_presupuestaria_id != null) {
                  setValue(
                    'partida_presupuestaria_id',
                    Number(sel.partida_presupuestaria_id),
                  )
                }
              }}
              error={errors.puesto_destino_id?.message}
              {...contained}
            />
          )}
        />

        <TextInput
          label="Lugar de trabajo"
          placeholder="Ej: Esmeraldas"
          error={errors.lugar_trabajo?.message}
          {...contained}
          {...register('lugar_trabajo')}
        />

        <Controller
          name="remuneracion_propuesta"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="R.M.U. propuesta"
              description={descripcionRmu}
              placeholder="0.00"
              min={0}
              decimalScale={2}
              readOnly={rmuHeredada}
              error={errors.remuneracion_propuesta?.message}
              value={field.value ?? ''}
              onChange={(v) => {
                const n = typeof v === 'number' ? v : parseFloat(String(v))
                field.onChange(Number.isFinite(n) ? n : null)
              }}
              {...contained}
            />
          )}
        />

        <Controller
          name="partida_presupuestaria_id"
          control={control}
          render={({ field }) => (
            <SelectPartidaPresupuestaria
              value={field.value}
              onChange={field.onChange}
              // La partida la decide la modalidad, no el puesto: un ocasional y
              // un permanente sobre la misma plaza se imputan distinto. Se pasa
              // el mismo régimen que manda en la R.M.U. —el propuesto en un
              // ingreso, el vigente cuando solo se reubica—, y no
              // `tipo_nombramiento_propuesto` a secas: fuera del ingreso llega
              // vacío, y el selector caía al catálogo completo justo donde la
              // modalidad más distingue (un provisional, un ocasional y un
              // profesional se imputan a partidas distintas).
              modalidad={regimenDeLaRmu}
            />
          )}
        />

        {esIngreso && <MovimientoDatosContratacion form={form} />}
      </Stack>
    </BloqueDetalle>
  )
}
