'use client'

import { Alert, Box, Grid, LoadingOverlay, Select } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { getApiErrorMessage } from '@/types/api'
import { useTodasUnidades } from '../hooks/useUnidades'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { useCargos } from '../hooks/useCargos'
import { REGIMENES_PUESTO, type RegimenPuesto } from '../constants/clasificacionPuesto'
import { SelectPartidaPresupuestaria } from './SelectPartidaPresupuestaria'
import { PuestoClasificacionCampos } from './PuestoClasificacionCampos'
import { PuestoPlazasCampos } from './PuestoPlazasCampos'
import { puestoSchema, type PuestoFormData } from '../schemas/puesto.schema'

interface Props {
  valoresIniciales?: Partial<PuestoFormData>
  /**
   * Con un ocupante, la unidad cambia con el traspaso de puesto, el régimen no
   * cambia y no se desactiva (fase 7.1a). El backend lo exige igual.
   */
  ocupado?: boolean
  /** Guarda; si el backend rechaza un campo, el error vuelve a ese campo. */
  onSubmit: (valores: PuestoFormData) => Promise<unknown>
  /** El título de la notificación cuando el error no es de un campo. */
  tituloDelFallo: string
}

const SOLO_CON_TRASPASO = 'Ocupado: pasa a otra unidad con «Traspasar a otra unidad».'
const SOLO_CON_REVISION = 'Ocupado: cambia con «Revisar la clasificación».'

export function PuestoForm({ valoresIniciales, ocupado = false, onSubmit, tituloDelFallo }: Props) {
  const contained = useContainedInput()
  const unidades = useTodasUnidades({ nivel: 2 })
  const grupos   = useGruposOcupacionales()
  const cargos   = useCargos()
  const cargando = unidades.isLoading || grupos.isLoading || cargos.isLoading

  const { control, handleSubmit, setValue, getValues, setError, formState: { errors } } = useForm<PuestoFormData>({
    resolver: zodResolver(puestoSchema) as Resolver<PuestoFormData>,
    defaultValues: {
      cargo_id:                  valoresIniciales?.cargo_id                  ?? undefined,
      unidad_administrativa_id:  valoresIniciales?.unidad_administrativa_id  ?? undefined,
      grupo_ocupacional_id:      valoresIniciales?.grupo_ocupacional_id      ?? null,
      partida_presupuestaria_id: valoresIniciales?.partida_presupuestaria_id ?? null,
      tipo:                      valoresIniciales?.tipo                      ?? 'plantilla',
      // `undefined` es un puesto nuevo; `null`, uno de contrato, que no tiene.
      plazas:                    valoresIniciales?.plazas === undefined ? 1 : valoresIniciales.plazas,
      rol_puesto:                valoresIniciales?.rol_puesto                ?? null,
      nivel_complejidad:         valoresIniciales?.nivel_complejidad         ?? null,
      regimen_laboral:           valoresIniciales?.regimen_laboral           ?? 'losep',
      es_jefe:                   valoresIniciales?.es_jefe                   ?? false,
      activo:                    valoresIniciales?.activo                    ?? true,
    },
  })

  const regimen = useWatch({ control, name: 'regimen_laboral', defaultValue: 'losep' })

  /**
   * Al cambiar de régimen se va lo que no le corresponde. El grupo LOSEP de un
   * puesto que pasa a Código del Trabajo quedaba oculto pero se enviaba, y el
   * backend lo rechazaba sobre un campo que no se veía.
   */
  const limpiarSegunRegimen = (nuevo: RegimenPuesto) => {
    if (nuevo === 'codigo_trabajo') setValue('grupo_ocupacional_id', null)
    const rol = getValues('rol_puesto')
    if (rol && (rol === 'codigo_trabajo') !== (nuevo === 'codigo_trabajo')) setValue('rol_puesto', null)
  }

  const guardar = async (valores: PuestoFormData) => {
    try {
      await onSubmit(valores)
    } catch (error) {
      // El 422 del backend va a su campo (regla 07); lo que no tiene campo
      // en el formulario se notifica, para que no falle en silencio.
      const campos = Object.entries(erroresDeCampo(error) ?? {})
      const delFormulario = campos.filter(([campo]) => campo in puestoSchema.shape)
      for (const [campo, mensaje] of delFormulario) {
        setError(campo as keyof PuestoFormData, { type: 'server', message: mensaje })
      }
      if (campos.length > delFormulario.length) notificar.error(tituloDelFallo, getApiErrorMessage(error))
    }
  }

  const opcionesCargo = (cargos.data ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))
  const opcionesUnidad = (unidades.data ?? []).map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  return (
    <Box pos="relative">
      <LoadingOverlay visible={cargando} zIndex={10} />
      <form id="puesto-form" onSubmit={handleSubmit(guardar)} noValidate>
        <Grid>
          {ocupado && (
            <Grid.Col span={12}>
              <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
                El puesto está ocupado. Pasa a otra unidad con «Traspasar a otra unidad» (LOSEP Art. 37),
                y cambia de grado o de denominación con «Revisar la clasificación» (Arts. 61 y 62): los
                dos emiten la acción de personal de cada ocupante. Mientras lo esté, el régimen no
                cambia, no se desactiva y las plazas no bajan de las ocupadas.
              </Alert>
            </Grid.Col>
          )}
          <Grid.Col span={{ base: 12, sm: 8 }}>
            <Controller
              name="cargo_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Cargo"
                  placeholder="Seleccione el cargo"
                  description={ocupado ? SOLO_CON_REVISION : undefined}
                  data={opcionesCargo}
                  searchable
                  allowDeselect={false}
                  disabled={ocupado}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.cargo_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="regimen_laboral"
              control={control}
              render={({ field }) => (
                <Select
                  label="Régimen laboral"
                  data={REGIMENES_PUESTO}
                  // Sin esto, pulsar el régimen ya elegido lo quitaba, y el
                  // valor caía a LOSEP sin que nadie lo pidiera.
                  allowDeselect={false}
                  disabled={ocupado}
                  {...contained}
                  value={field.value}
                  onChange={(v) => {
                    const elegido = REGIMENES_PUESTO.find((r) => r.value === v)?.value
                    if (!elegido) return
                    field.onChange(elegido)
                    limpiarSegunRegimen(elegido)
                  }}
                  error={errors.regimen_laboral?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <Controller
              name="unidad_administrativa_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Unidad administrativa"
                  placeholder="Seleccione la unidad"
                  description={ocupado ? SOLO_CON_TRASPASO : undefined}
                  data={opcionesUnidad}
                  searchable
                  allowDeselect={false}
                  disabled={ocupado}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.unidad_administrativa_id?.message}
                />
              )}
            />
          </Grid.Col>
          <PuestoClasificacionCampos
            control={control}
            errors={errors}
            setValue={setValue} regimen={regimen}
            grupos={grupos.data ?? []}
            ocupado={ocupado}
          />
          <Grid.Col span={12}>
            <Controller
              name="partida_presupuestaria_id"
              control={control}
              render={({ field }) => (
                <SelectPartidaPresupuestaria
                  value={field.value}
                  onChange={field.onChange}
                  description="Partida que respalda la remuneración de este puesto."
                  error={errors.partida_presupuestaria_id?.message}
                />
              )}
            />
          </Grid.Col>
          <PuestoPlazasCampos control={control} errors={errors} setValue={setValue} ocupado={ocupado} />
        </Grid>
      </form>
    </Box>
  )
}
