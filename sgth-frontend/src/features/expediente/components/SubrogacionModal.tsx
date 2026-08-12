'use client'

import {
  Modal, Button, Group, Stack, Select, TextInput, Textarea, SegmentedControl,
  Grid, Paper, Text, Alert,
} from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { useServidores } from '../hooks/useServidores'
import { useSubrogacionMutations } from '../hooks/useSubrogacionMutations'
import { SituacionActualPanel } from './SituacionActualPanel'
import { SituacionSubrogadaPanel } from './SituacionSubrogadaPanel'
import {
  subrogacionSchema, type SubrogacionFormData,
} from '../schemas/subrogacion.schema'
import type { UnidadConRelaciones, PuestoConRelaciones, ServidorConRelaciones } from '@/types/api'

const TIPO_OPTIONS = [
  { value: 'subrogacion', label: 'Subrogación' },
  { value: 'encargo',     label: 'Encargo' },
]

const MOTIVO_OPTIONS = [
  { value: 'vacaciones',          label: 'Vacaciones' },
  { value: 'comision_servicios',  label: 'Comisión de Servicios' },
  { value: 'enfermedad',          label: 'Enfermedad' },
  { value: 'licencia',            label: 'Licencia' },
  { value: 'encargo_vacante',     label: 'Encargo por Vacante' },
  { value: 'otro',                label: 'Otro' },
]

const BLANK_VALUES: SubrogacionFormData = {
  tipo: 'subrogacion',
  servidor_subrogante_id: undefined as unknown as number,
  servidor_subrogado_id:  null,
  unidad_administrativa_id: undefined as unknown as number,
  puesto_subrogado_id:      undefined as unknown as number,
  fecha_inicio: '',
  fecha_fin:    '',
  motivo: 'vacaciones',
  resolucion_numero: '',
  observacion: '',
}

interface Props {
  opened: boolean
  onClose: () => void
}

export function SubrogacionModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { registrar } = useSubrogacionMutations()

  const {
    control, handleSubmit, reset, register, setValue,
    formState: { errors },
  } = useForm<SubrogacionFormData>({
    resolver: zodResolver(subrogacionSchema),
    defaultValues: BLANK_VALUES,
  })

  const tipo = useWatch({ control, name: 'tipo' })
  const unidadSelId = useWatch({ control, name: 'unidad_administrativa_id' })
  const subroganteId = useWatch({ control, name: 'servidor_subrogante_id' })
  const subrogadoId = useWatch({ control, name: 'servidor_subrogado_id' })
  const puestoSelId = useWatch({ control, name: 'puesto_subrogado_id' })

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: puestosData } = usePuestos(
    unidadSelId ? { unidad_administrativa_id: Number(unidadSelId), per_page: 100 } : undefined
  )
  const puestos = (puestosData?.data ?? []) as PuestoConRelaciones[]

  const { data: servidoresData } = useServidores({ per_page: 500 })
  const servidores = (servidoresData?.data ?? []) as ServidorConRelaciones[]

  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))
  const puestoOptions = puestos.map((p) => ({ value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}` }))
  const servidorOptions = servidores.map((s) => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(' ')} — ${s.cedula}`,
  }))

  const unidadSel = unidades.find((u) => u.id === Number(unidadSelId)) ?? null
  const puestoSel = puestos.find((p) => p.id === Number(puestoSelId)) ?? null

  // La diferencia se calcula contra lo que el subrogante gana hoy, que vive en
  // su contrato vigente; si no lo tiene, se cae a la R.M.U. de su puesto.
  const subroganteSel = servidores.find((s) => s.id === Number(subroganteId))
  const rmuSubroganteRaw = subroganteSel?.contrato_vigente?.remuneracion
    ?? (subroganteSel as unknown as { puesto?: { rmu?: string | number | null } })?.puesto?.rmu
  const rmuSubrogante = rmuSubroganteRaw != null ? Number(rmuSubroganteRaw) : null

  /**
   * El titular no se pide: es quien ocupa el puesto. Pedirlo aparte permitía
   * nombrar titular a alguien que nunca ocupó ese puesto, y el documento
   * firmado terminaba afirmando un reemplazo que no ocurrió.
   */
  const ocupanteDe = (puestoId?: number | null) =>
    puestos.find((p) => p.id === Number(puestoId))?.ocupantes?.[0] ?? null

  const ocupante = ocupanteDe(puestoSelId)
  const puestoVacante = puestoSel != null && ocupante == null
  const nombrePuesto = puestoSel?.cargo?.nombre ?? 'el puesto'

  /**
   * Cambiar de figura arrastra al titular: en encargo no hay a quién
   * reemplazar, y en subrogación el titular vuelve a ser el del puesto.
   */
  const elegirTipo = (valor: string) => {
    setValue('tipo', valor as 'subrogacion' | 'encargo')
    setValue(
      'servidor_subrogado_id',
      valor === 'encargo' ? null : (ocupanteDe(puestoSelId)?.id ?? null),
    )
  }

  // La figura la determina el puesto, no quien llena el formulario: un puesto
  // vacante se encarga y uno con titular se subroga. Se avisa en vez de dejar
  // que el backend lo rechace al guardar.
  const figuraEquivocada =
    puestoSel != null && (tipo === 'subrogacion' ? puestoVacante : !puestoVacante)

  const handleClose = () => {
    reset(BLANK_VALUES)
    onClose()
  }

  const toDate = (v?: string | null): Date | null => {
    if (!v) return null
    const [year, month, day] = v.split('T')[0].split('-').map(Number)
    return new Date(year, month - 1, day)
  }
  const fromDate = (d: Date | string | null): string | null => {
    if (!d) return null
    const date = typeof d === 'string' ? toDate(d) : d
    if (!date || isNaN(date.getTime())) return null
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  }

  const onSubmit = (values: SubrogacionFormData) => {
    registrar.mutate(values, { onSuccess: handleClose })
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Nueva subrogación / encargo"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />}>
            Queda <strong>pendiente de aprobación</strong>: el servidor asume el
            puesto —y con él la facultad de firmar— recién cuando su Acción de
            Personal se registre, con el dictamen presupuestario correspondiente.
          </Alert>

          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <SegmentedControl
                data={TIPO_OPTIONS}
                value={field.value}
                onChange={elegirTipo}
                fullWidth
              />
            )}
          />

          <Controller
            name="servidor_subrogante_id"
            control={control}
            render={({ field }) => (
              <Select
                label={tipo === 'encargo' ? 'Servidor encargado' : 'Servidor subrogante'}
                placeholder="Seleccionar servidor"
                data={servidorOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.servidor_subrogante_id?.message}
              />
            )}
          />

          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                placeholder="Seleccionar unidad"
                data={unidadOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : undefined)
                  setValue('puesto_subrogado_id', undefined as unknown as number)
                }}
                error={errors.unidad_administrativa_id?.message}
              />
            )}
          />

          <Controller
            name="puesto_subrogado_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Puesto"
                placeholder={unidadSelId ? 'Seleccionar puesto' : 'Seleccione primero la unidad'}
                data={puestoOptions}
                searchable
                disabled={!unidadSelId}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  const id = v ? Number(v) : undefined
                  field.onChange(id)
                  // De aquí sale el titular, así que no puede quedar el del
                  // puesto anterior.
                  setValue(
                    'servidor_subrogado_id',
                    tipo === 'encargo' ? null : (ocupanteDe(id)?.id ?? null),
                  )
                }}
                error={errors.puesto_subrogado_id?.message}
              />
            )}
          />

          {figuraEquivocada && (
            <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16} />}>
              {puestoVacante ? (
                <>
                  <strong>{nombrePuesto}</strong> está vacante: no hay titular a
                  quien subrogar. La figura que corresponde es el encargo.
                </>
              ) : (
                <>
                  <strong>{nombrePuesto}</strong> lo ocupa {ocupante?.nombre}:
                  la figura que corresponde es la subrogación.
                </>
              )}
              <Button
                size="xs"
                variant="light"
                color="orange"
                mt="xs"
                onClick={() => elegirTipo(puestoVacante ? 'encargo' : 'subrogacion')}
              >
                Cambiar a {puestoVacante ? 'encargo' : 'subrogación'}
              </Button>
            </Alert>
          )}

          {/* Las tres situaciones del acto: de dónde viene quien subroga, a
              quién reemplaza y qué puesto asume. Sin esto, Talento Humano
              autorizaba a ciegas — en particular la diferencia de
              remuneraciones, que es lo que realmente se paga. */}
          {(subroganteId || puestoSel) && (
            <Grid mt="xs">
              {subroganteId && (
                <Grid.Col span={{ base: 12, md: 4 }}>
                  <SituacionActualPanel
                    servidorId={Number(subroganteId)}
                    titulo={tipo === 'encargo' ? 'SITUACIÓN DEL ENCARGADO' : 'SITUACIÓN DEL SUBROGANTE'}
                    soloVinculo
                  />
                </Grid.Col>
              )}

              <Grid.Col span={{ base: 12, md: 4 }}>
                {tipo === 'encargo' ? (
                  <Paper withBorder p="sm" radius="md" h="100%" bg="var(--mantine-color-gray-0)">
                    <Text size="sm" fw={700} mb="xs">TITULAR</Text>
                    <Text size="sm" c="dimmed">
                      Encargo: el puesto no tiene titular que reemplazar.
                    </Text>
                  </Paper>
                ) : subrogadoId ? (
                  <SituacionActualPanel
                    servidorId={Number(subrogadoId)}
                    titulo="TITULAR SUBROGADO"
                    soloVinculo
                  />
                ) : (
                  <Paper withBorder p="sm" radius="md" h="100%" bg="var(--mantine-color-gray-0)">
                    <Text size="sm" fw={700} mb="xs">TITULAR SUBROGADO</Text>
                    <Text size="sm" c="dimmed">
                      {/* Ya no se elige: sale del puesto. */}
                      {puestoSel
                        ? 'El puesto está vacante — no hay titular.'
                        : 'Seleccione el puesto: el titular es quien lo ocupa.'}
                    </Text>
                  </Paper>
                )}
              </Grid.Col>

              <Grid.Col span={{ base: 12, md: 4 }}>
                <SituacionSubrogadaPanel
                  unidad={unidadSel}
                  puesto={puestoSel}
                  rmuSubrogante={rmuSubrogante}
                />
              </Grid.Col>
            </Grid>
          )}

          <Group grow>
            <Controller
              name="fecha_inicio"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha de inicio"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d) ?? '')}
                  error={errors.fecha_inicio?.message}
                />
              )}
            />
            <Controller
              name="fecha_fin"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha de fin"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d) ?? '')}
                  error={errors.fecha_fin?.message}
                />
              )}
            />
          </Group>

          <Controller
            name="motivo"
            control={control}
            render={({ field }) => (
              <Select
                label="Motivo"
                data={MOTIVO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'otro')}
                error={errors.motivo?.message}
              />
            )}
          />

          <TextInput
            label="Número de resolución"
            placeholder="Opcional"
            {...contained}
            {...register('resolucion_numero')}
            error={errors.resolucion_numero?.message}
          />

          <Textarea
            label="Observación"
            placeholder="Opcional"
            minRows={2}
            {...contained}
            {...register('observacion')}
            error={errors.observacion?.message}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>Cancelar</Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={registrar.isPending}
              // El backend lo rechaza igual; esto evita ofrecer un guardado
              // que ya se sabe que va a fallar.
              disabled={figuraEquivocada}
            >
              Registrar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
