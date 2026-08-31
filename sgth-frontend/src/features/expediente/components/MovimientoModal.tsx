'use client'

import React from 'react'
import {
  Modal, Button, Group, Stack, Select, Textarea, TextInput, Alert,
  Switch, Divider, Stepper, Grid, NumberInput, Paper, Text,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, useWatch, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck, IconX, IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQueryClient, useMutation } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { expedienteService } from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { movimientoSchema, type MovimientoFormData } from '../schemas/movimiento.schema'
import { SituacionActualPanel } from './SituacionActualPanel'
import { admiteMarcacion, esLosep, remuneracionEsHeredada } from '../utils/nombramiento'
import { TIPO_NOMBRAMIENTO_OPTIONS } from '../utils/tipoNombramientoOptions'
import { useAusenciasTemporales } from '../hooks/useAusenciasTemporales'
import { FirmantesPanel } from './FirmantesPanel'
import {
  SUBTIPO_LABELS, TIPO_LABELS, esComision, reubicaAlServidor,
  requiereDictamenPorDefecto, requiereSubtipo, subtiposElegibles, tiposElegibles,
  type AccionSubtipo, type AccionTipo,
} from '../utils/taxonomiaAccionPersonal'
import type { MovimientoPersonal, UnidadConRelaciones, PuestoConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  tipoNombramiento?: string | null
  /**
   * Presente = modo edición sobre un borrador. El mismo formulario sirve para
   * crear y para corregir: separarlos era lo que producía dos pantallas con
   * campos distintos y ninguna con todos.
   */
  movimiento?: MovimientoPersonal | null
  /**
   * Crea con el tipo ya decidido y sin paso de selección. Lo usa Ingreso y
   * Vinculación, que se registra desde su propia pantalla y no compite con los
   * demás tipos: el servidor todavía no tiene vínculo sobre el que actuar.
   */
  tipoFijo?: AccionTipo
  /** Encabezado del modal cuando el contexto ya dice de quién se trata. */
  titulo?: string
}

const BLANK: Partial<MovimientoFormData> = {
  descripcion: '',
  fecha_efectiva: '',
  fecha_inicio: null,
  fecha_fin: null,
  unidad_destino_id: null,
  puesto_destino_id: null,
  remuneracion_propuesta: null,
  partida_presupuestaria_id: null,
  lugar_trabajo: '',
  resolucion_numero: '',
  observacion: '',
  caucionado: false,
  caucion_numero: '',
  caucion_fecha: null,
  requiere_dictamen_medico: false,
  tipo_nombramiento_propuesto: null,
  numero_contrato: '',
  fecha_fin_propuesta: null,
  puede_marcar: false,
  cubre_movimiento_id: null,
}

/**
 * Un reemplazo es transitorio por definición: dura lo que dura la ausencia del
 * titular. La misma regla la impone el backend en validarReemplazo().
 */
const NOMBRAMIENTOS_DE_REEMPLAZO = ['servicios_ocasionales', 'servicios_profesionales']

function fechaCorta(f?: string | null): string {
  if (!f) return 'sin fin'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('T')[0].split('-').map(Number)
  return new Date(y, m - 1, d)
}

const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function MovimientoModal({
  opened, onClose, servidorId, tipoNombramiento, movimiento = null,
  tipoFijo, titulo,
}: Props) {
  const { isMobile } = useMobileBreakpoint()

  const encabezado = titulo
    ?? (movimiento
      ? 'Editar acción de personal en borrador'
      : tipoFijo
        ? TIPO_LABELS[tipoFijo]
        : 'Registrar acción de personal')

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={encabezado}
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      {/* Se monta al abrir, así el formulario arranca limpio sin resetear
          estado desde un efecto. */}
      {opened && (
        <FormularioAccion
          key={movimiento?.id ?? tipoFijo ?? 'nuevo'}
          servidorId={servidorId}
          tipoNombramiento={tipoNombramiento}
          movimiento={movimiento}
          tipoFijo={tipoFijo}
          onClose={onClose}
        />
      )}
    </Modal>
  )
}

function FormularioAccion({
  servidorId,
  tipoNombramiento,
  movimiento,
  tipoFijo,
  onClose,
}: {
  servidorId: number
  tipoNombramiento?: string | null
  movimiento?: MovimientoPersonal | null
  tipoFijo?: AccionTipo
  onClose: () => void
}) {
  const contained = useContainedInput()
  const qc = useQueryClient()

  const edicion = !!movimiento
  /**
   * Sin paso de selección: al editar, porque cambiar la naturaleza del acto no
   * es editarlo; y con tipo fijo, porque ya venía decidido desde la pantalla.
   *
   * Salvo que ese tipo exija subtipo —cesación, cambio administrativo, régimen
   * disciplinario—: ahí el paso sigue haciendo falta, porque es el subtipo el
   * que determina las reglas y el documento. Saltárselo dejaba un formulario
   * que el backend rechazaba por un dato que nunca se pidió.
   */
  const sinPasoDeTipo = edicion || (!!tipoFijo && !requiereSubtipo(tipoFijo))

  const [paso, setPaso] = React.useState(sinPasoDeTipo ? 1 : 0)

  const tipos = tiposElegibles(tipoNombramiento)

  const iniciales: Partial<MovimientoFormData> = edicion
    ? {
      tipo_movimiento: movimiento!.tipo_movimiento as AccionTipo,
      subtipo_movimiento: (movimiento!.subtipo_movimiento ?? null) as AccionSubtipo | null,
      descripcion: movimiento!.descripcion ?? '',
      fecha_efectiva: movimiento!.fecha_efectiva?.split('T')[0] ?? '',
      fecha_inicio: movimiento!.fecha_inicio?.split('T')[0] ?? null,
      fecha_fin: movimiento!.fecha_fin?.split('T')[0] ?? null,
      unidad_destino_id: movimiento!.unidad_destino_id ?? null,
      puesto_destino_id: movimiento!.puesto_destino_id ?? null,
      remuneracion_propuesta: movimiento!.remuneracion_propuesta != null
        ? Number(movimiento!.remuneracion_propuesta) : null,
      partida_presupuestaria_id: movimiento!.partida_presupuestaria_id ?? null,
      lugar_trabajo: movimiento!.lugar_trabajo ?? '',
      tipo_nombramiento_propuesto: movimiento!.tipo_nombramiento_propuesto ?? null,
      numero_contrato: movimiento!.numero_contrato ?? '',
      fecha_fin_propuesta: movimiento!.fecha_fin_propuesta?.split('T')[0] ?? null,
      puede_marcar: movimiento!.puede_marcar ?? false,
      cubre_movimiento_id: movimiento!.cubre_movimiento_id ?? null,
      requiere_dictamen_medico: movimiento!.requiere_dictamen_medico ?? false,
      resolucion_numero: movimiento!.resolucion_numero ?? '',
      observacion: movimiento!.observacion ?? '',
      caucionado: movimiento!.caucionado ?? false,
      caucion_numero: movimiento!.caucion_numero ?? '',
      caucion_fecha: movimiento!.caucion_fecha?.split('T')[0] ?? null,
    }
    : { ...BLANK, tipo_movimiento: tipoFijo ?? tipos[0] }

  const {
    control, handleSubmit, reset, register, setValue,
    formState: { errors },
  } = useForm<MovimientoFormData>({
    resolver: zodResolver(movimientoSchema),
    defaultValues: iniciales as MovimientoFormData,
  })

  const tipo = useWatch({ control, name: 'tipo_movimiento' }) as AccionTipo | undefined
  const subtipo = useWatch({ control, name: 'subtipo_movimiento' }) as AccionSubtipo | null | undefined
  const caucionado = useWatch({ control, name: 'caucionado' })
  const unidadDestinoId = useWatch({ control, name: 'unidad_destino_id' })
  const puestoDestinoId = useWatch({ control, name: 'puesto_destino_id' })

  const subtipos = tipo ? subtiposElegibles(tipo, tipoNombramiento) : []
  const esIngreso = tipo === 'ingreso'
  // El ingreso siempre propone puesto y unidad: es donde nace el vínculo.
  const muestraPropuesta = reubicaAlServidor(subtipo) || esIngreso
  const muestraFechas = esComision(subtipo)

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: puestosData } = usePuestos(
    unidadDestinoId ? { unidad_administrativa_id: Number(unidadDestinoId), per_page: 100 } : undefined,
  )
  const puestos = (puestosData?.data ?? []) as PuestoConRelaciones[]

  // Aquí el régimen es el del vínculo vigente del servidor: estas acciones no
  // cambian de nombramiento, reubican dentro del que ya tiene.
  const puestoDestino = puestos.find((p) => p.id === Number(puestoDestinoId))
  const rmuHeredada = remuneracionEsHeredada(
    tipoNombramiento,
    puestoDestino?.rmu != null ? Number(puestoDestino.rmu) : null,
  )

  // ── Reemplazo: solo aplica al ingreso con nombramiento temporal ──
  const nombramiento = useWatch({ control, name: 'tipo_nombramiento_propuesto' })
  const cubreId = useWatch({ control, name: 'cubre_movimiento_id' })

  const puedeCubrir = esIngreso && NOMBRAMIENTOS_DE_REEMPLAZO.includes(nombramiento ?? '')

  // Solo las que hoy siguen sin cubrir: ofrecer una ya cubierta serviría solo
  // para que el backend la rechace.
  const { data: ausencias = [] } = useAusenciasTemporales({ cubiertas: false })

  const ausenciaOptions = ausencias.map((a) => ({
    value: String(a.id),
    label: `${a.servidor.nombre} — ${a.etiqueta ?? 'Ausencia'} (hasta ${fechaCorta(a.hasta)})`,
  }))

  const ausenciaSel = ausencias.find((a) => a.id === Number(cubreId))

  const descripcionRmu = rmuHeredada
    ? 'Fijada por el grupo ocupacional del puesto destino. No se edita en régimen LOSEP.'
    : esLosep(tipoNombramiento)
      ? (puestoDestinoId
        ? 'Este puesto no tiene grupo ocupacional asignado, así que no hay monto que heredar: ingréselo a mano.'
        : 'Elija el puesto destino para heredar la remuneración de su grupo ocupacional.')
      : 'Se pacta en el contrato: este régimen no toma la remuneración del puesto.'

  const handleClose = () => {
    reset(BLANK as MovimientoFormData)
    onClose()
  }

  /**
   * El dictamen médico viene pre-marcado en jubilación e incapacidad, igual
   * que en el backend, y sigue siendo editable. Se deriva aquí y no en un
   * efecto: es consecuencia directa de elegir el subtipo, no una
   * sincronización con nada externo.
   */
  const elegirSubtipo = (valor: AccionSubtipo | null) => {
    setValue('subtipo_movimiento', valor)
    setValue('requiere_dictamen_medico', requiereDictamenPorDefecto(valor))
  }

  /**
   * Los campos de contratación solo viajan en el ingreso. Enviarlos en un
   * traspaso los grabaría en una acción que nunca creará un contrato, y el
   * documento impreso acabaría con un número que no corresponde a nada.
   */
  const soloLoQueAplica = (data: MovimientoFormData): MovimientoFormData => {
    if (data.tipo_movimiento === 'ingreso') return data

    return {
      ...data,
      numero_contrato: null,
      fecha_fin_propuesta: null,
      puede_marcar: null,
      tipo_nombramiento_propuesto: null,
      cubre_movimiento_id: null,
    }
  }

  const guardar = useMutation({
    mutationFn: (data: MovimientoFormData) => {
      const limpio = soloLoQueAplica(data)

      if (edicion) {
        // tipo y subtipo no se envían: el backend los rechaza en la edición
        // porque cambiar la naturaleza del acto exige anular y registrar otro.
        const editable = { ...limpio }
        delete (editable as Partial<MovimientoFormData>).tipo_movimiento
        delete (editable as Partial<MovimientoFormData>).subtipo_movimiento

        return expedienteService.actualizarBorradorMovimiento(movimiento!.id, editable)
      }

      return expedienteService.crearMovimiento(servidorId, limpio)
    },
    onSuccess: () => {
      notifications.show({
        title: edicion ? 'Borrador actualizado' : 'Acción de personal registrada',
        message: edicion
          ? 'Los cambios quedaron guardados en la acción de personal.'
          : 'Quedó en borrador, pendiente de revisión y aprobación.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      handleClose()
    },
    onError: (error) => {
      notifications.show({
        title: edicion ? 'No se pudo guardar' : 'No se pudo registrar',
        message: getApiErrorMessage(
          error,
          edicion
            ? 'No se pudo actualizar el borrador.'
            : 'No se pudo registrar la acción de personal.',
        ),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

  const puedeAvanzar = !!tipo && (!requiereSubtipo(tipo) || !!subtipo)

  return (
    <>
      {!sinPasoDeTipo && tipos.length === 0 ? (
        <Alert icon={<IconInfoCircle size={16} />} color="yellow" variant="light">
          El servidor no tiene un contrato vigente elegible para ninguna acción de
          personal formal, o no tiene contrato vigente registrado.
        </Alert>
      ) : (
        <form onSubmit={handleSubmit((v) => guardar.mutate(v))} noValidate>
          <Stepper
            active={paso}
            onStepClick={sinPasoDeTipo ? undefined : setPaso}
            size="sm"
            color="emerald"
          >
            {/* Con el tipo ya fijado, lo único que queda por elegir aquí es el
                subtipo — y el rótulo debe decirlo solo cuando de verdad lo
                haya. */}
            <Stepper.Step
              label={tipoFijo && requiereSubtipo(tipoFijo) ? 'Subtipo' : 'Tipo de acción'}
              description={
                tipoFijo && requiereSubtipo(tipoFijo)
                  ? 'Bajo qué figura'
                  : 'Qué se va a registrar'
              }
            >
              <Stack gap="sm" mt="md">
                {/* Con tipo fijo no se vuelve a preguntar: ya se eligió en el
                    grid, y ofrecerlo otra vez permitiría contradecirlo. */}
                {!tipoFijo && (
                  <Controller
                    name="tipo_movimiento"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Tipo de acción de personal"
                        data={tipos.map((t) => ({ value: t, label: TIPO_LABELS[t] }))}
                        value={field.value}
                        onChange={(v) => {
                          field.onChange(v as AccionTipo)
                          elegirSubtipo(null)
                        }}
                        error={errors.tipo_movimiento?.message}
                        {...contained}
                      />
                    )}
                  />
                )}

                {tipo && requiereSubtipo(tipo) && (
                  <Controller
                    name="subtipo_movimiento"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Subtipo"
                        placeholder="Seleccione el subtipo"
                        description="Es el subtipo el que determina las reglas y el documento que se imprime."
                        data={subtipos.map((s) => ({ value: s, label: SUBTIPO_LABELS[s] }))}
                        value={field.value ?? null}
                        onChange={(v) => elegirSubtipo(v as AccionSubtipo | null)}
                        error={errors.subtipo_movimiento?.message}
                        {...contained}
                      />
                    )}
                  />
                )}

                {tipo && requiereSubtipo(tipo) && subtipos.length === 0 && (
                  <Alert color="yellow" variant="light" icon={<IconInfoCircle size={16} />}>
                    Ningún subtipo de {TIPO_LABELS[tipo]} aplica al nombramiento vigente
                    de este servidor.
                  </Alert>
                )}

                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleClose}>Cancelar</Button>
                  <Button color="emerald" disabled={!puedeAvanzar} onClick={() => setPaso(1)}>
                    Continuar
                  </Button>
                </Group>
              </Stack>
            </Stepper.Step>

            <Stepper.Step label="Detalle" description="Datos de la acción">
              <Stack gap="sm" mt="md">
                {muestraPropuesta ? (
                  <Grid>
                    <Grid.Col span={{ base: 12, md: 6 }}>
                      <SituacionActualPanel servidorId={servidorId} />
                    </Grid.Col>
                    <Grid.Col span={{ base: 12, md: 6 }}>
                      <Paper withBorder p="sm" radius="md">
                        <Text size="sm" fw={700} mb="xs">SITUACIÓN PROPUESTA</Text>
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
                                  // La partida del puesto es la sugerencia, no
                                  // una imposición: el campo queda editable
                                  // porque Talento Humano puede respaldar el
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
                                // La partida la decide la modalidad, no el
                                // puesto: un ocasional y un permanente sobre
                                // la misma plaza se imputan distinto.
                                modalidad={nombramiento}
                              />
                            )}
                          />

                          {/* Solo el ingreso da origen a un contrato. En un
                              traspaso o una comisión estos campos ni se
                              muestran ni se envían: no hay instrumento nuevo
                              que numerar. */}
                          {esIngreso && (
                            <>
                              <Controller
                                name="tipo_nombramiento_propuesto"
                                control={control}
                                render={({ field }) => (
                                  <Select
                                    label="Tipo de nombramiento"
                                    data={TIPO_NOMBRAMIENTO_OPTIONS}
                                    searchable
                                    value={field.value ?? null}
                                    onChange={(v) => {
                                      field.onChange(v)
                                      // Al salir de un nombramiento temporal el
                                      // enlace de reemplazo deja de ser válido.
                                      if (!NOMBRAMIENTOS_DE_REEMPLAZO.includes(v ?? '')) {
                                        setValue('cubre_movimiento_id', null)
                                      }
                                    }}
                                    error={errors.tipo_nombramiento_propuesto?.message}
                                    {...contained}
                                  />
                                )}
                              />

                              {/* Un reemplazo dura lo que dura la ausencia, así
                                  que solo se ofrece con nombramiento temporal.
                                  La misma regla la impone validarReemplazo(). */}
                              {puedeCubrir && (
                                <Controller
                                  name="cubre_movimiento_id"
                                  control={control}
                                  render={({ field }) => (
                                    <Select
                                      label="¿Cubre una ausencia temporal?"
                                      description="Enlaza este ingreso con la comisión o licencia cuyo hueco viene a cubrir. El titular conserva su plaza."
                                      placeholder={ausenciaOptions.length === 0
                                        ? 'No hay ausencias sin cubrir'
                                        : 'Ninguna — es un ingreso ordinario'}
                                      data={ausenciaOptions}
                                      disabled={ausenciaOptions.length === 0}
                                      searchable
                                      clearable
                                      value={field.value ? String(field.value) : null}
                                      onChange={(v) => {
                                        field.onChange(v ? Number(v) : null)

                                        // El suplente entra a la plaza del
                                        // ausente, no a otra.
                                        const sel = ausencias.find((a) => String(a.id) === v)
                                        if (sel?.unidad_id) setValue('unidad_destino_id', sel.unidad_id)
                                        if (sel?.puesto_id) setValue('puesto_destino_id', sel.puesto_id)
                                      }}
                                      {...contained}
                                    />
                                  )}
                                />
                              )}

                              {ausenciaSel && (
                                <Alert variant="light" color="violet" icon={<IconInfoCircle size={16} />}>
                                  Reemplaza a <strong>{ausenciaSel.servidor.nombre}</strong> en{' '}
                                  {ausenciaSel.puesto ?? 'su puesto'}. El contrato no puede
                                  pasar del {fechaCorta(ausenciaSel.hasta)}, que es cuando regresa.
                                </Alert>
                              )}
                              <TextInput
                                label="Número de contrato"
                                placeholder="Ej: CT-2026-0099"
                                {...contained}
                                {...register('numero_contrato')}
                              />
                              <Controller
                                name="fecha_fin_propuesta"
                                control={control}
                                render={({ field }) => (
                                  <DatePickerInput
                                    label="Fecha de término del contrato"
                                    description="Servicios Profesionales toma el 31 de diciembre de su año si se deja vacío."
                                    valueFormat="DD/MM/YYYY"
                                    clearable
                                    value={toDate(field.value)}
                                    onChange={(d) => field.onChange(fromDate(d as Date | null))}
                                    {...contained}
                                  />
                                )}
                              />
                              <Controller
                                name="puede_marcar"
                                control={control}
                                render={({ field }) => {
                                  // Servicios profesionales, libre nombramiento
                                  // y elección popular no marcan nunca: el
                                  // interruptor se apaga y se bloquea, y el
                                  // backend fuerza el valor igualmente.
                                  const admite = admiteMarcacion(nombramiento)

                                  return (
                                    <Switch
                                      label="Marcación biométrica"
                                      description={admite
                                        ? 'Sugerida según el nombramiento; ajústela si este caso es distinto.'
                                        : 'Esta modalidad no marca biométrico.'}
                                      checked={admite && !!field.value}
                                      disabled={!admite}
                                      onChange={(e) => field.onChange(e.currentTarget.checked)}
                                    />
                                  )
                                }}
                              />
                            </>
                          )}
                        </Stack>
                      </Paper>
                    </Grid.Col>
                  </Grid>
                ) : null}

                <Textarea
                  label="Explicación"
                  placeholder="Detalle y justificación de la acción de personal"
                  minRows={3}
                  {...contained}
                  {...register('descripcion')}
                  error={errors.descripcion?.message}
                />

                <Controller
                  name="fecha_efectiva"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Rige a partir de"
                      placeholder="Seleccionar fecha"
                      valueFormat="DD/MM/YYYY"
                      value={toDate(field.value)}
                      onChange={(d) => field.onChange(fromDate(d as Date | null) ?? '')}
                      error={errors.fecha_efectiva?.message}
                      {...contained}
                    />
                  )}
                />

                {muestraFechas && (
                  <>
                    <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
                      La comisión de servicios dura entre 1 y 6 años, y el servidor
                      necesita al menos 2 años de antigüedad en la institución.
                    </Alert>
                    <Group grow>
                      <Controller
                        name="fecha_inicio"
                        control={control}
                        render={({ field }) => (
                          <DatePickerInput
                            label="Desde"
                            valueFormat="DD/MM/YYYY"
                            value={toDate(field.value)}
                            onChange={(d) => field.onChange(fromDate(d as Date | null))}
                            error={errors.fecha_inicio?.message}
                            {...contained}
                          />
                        )}
                      />
                      <Controller
                        name="fecha_fin"
                        control={control}
                        render={({ field }) => (
                          <DatePickerInput
                            label="Hasta"
                            valueFormat="DD/MM/YYYY"
                            value={toDate(field.value)}
                            onChange={(d) => field.onChange(fromDate(d as Date | null))}
                            error={errors.fecha_fin?.message}
                            {...contained}
                          />
                        )}
                      />
                    </Group>
                  </>
                )}

                <TextInput
                  label="Número de resolución"
                  placeholder="Opcional"
                  {...contained}
                  {...register('resolucion_numero')}
                />

                <Controller
                  name="requiere_dictamen_medico"
                  control={control}
                  render={({ field }) => (
                    <Switch
                      label="Requiere ficha de salud ocupacional"
                      description="Si se marca, no podrá registrarse sin dictamen de aptitud del dispensario."
                      checked={!!field.value}
                      onChange={(e) => field.onChange(e.currentTarget.checked)}
                    />
                  )}
                />

                <Divider my={4} label="Firmarán este documento" labelPosition="left" />

                <Text size="xs" c="dimmed">
                  Se toman del organigrama y quedan sellados al suscribir la acción.
                </Text>
                <FirmantesPanel compacto />

                <Divider my={4} label="Caución" labelPosition="left" />

                <Controller
                  name="caucionado"
                  control={control}
                  render={({ field }) => (
                    <Switch
                      label="El puesto exige caución"
                      checked={!!field.value}
                      onChange={(e) => field.onChange(e.currentTarget.checked)}
                    />
                  )}
                />

                {caucionado && (
                  <Group grow>
                    <TextInput
                      label="Caución registrada con No."
                      {...contained}
                      {...register('caucion_numero')}
                      error={errors.caucion_numero?.message}
                    />
                    <Controller
                      name="caucion_fecha"
                      control={control}
                      render={({ field }) => (
                        <DatePickerInput
                          label="Fecha"
                          valueFormat="DD/MM/YYYY"
                          value={toDate(field.value)}
                          onChange={(d) => field.onChange(fromDate(d as Date | null))}
                          {...contained}
                        />
                      )}
                    />
                  </Group>
                )}

                <Textarea
                  label="Observación"
                  placeholder="Opcional"
                  minRows={2}
                  {...contained}
                  {...register('observacion')}
                />

                <Group justify="space-between" mt="md">
                  {sinPasoDeTipo
                    ? <span />
                    : <Button variant="default" onClick={() => setPaso(0)}>Atrás</Button>}
                  <Group>
                    <Button variant="default" onClick={handleClose}>Cancelar</Button>
                    <Button type="submit" color="emerald" loading={guardar.isPending}>
                      {edicion ? 'Guardar cambios' : 'Registrar en borrador'}
                    </Button>
                  </Group>
                </Group>
              </Stack>
            </Stepper.Step>
          </Stepper>
        </form>
      )}
    </>
  )
}
