'use client'

import { useState } from 'react'
import {
  Modal, Button, Group, Stack,
  Select, Textarea, NumberInput, SegmentedControl,
  Checkbox, Text, Divider, Alert,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle, IconPlus } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useEppEntregaMutations, useKitEppServidor } from '../hooks/useEppEntregas'
import { useEquiposProteccion } from '../hooks/useEquiposProteccion'
import {
  eppEntregaSchema, type EppEntregaFormData, MOTIVO_ENTREGA_OPTIONS,
} from '../schemas/eppEntrega.schema'
import { toDateValue, fromDateValue } from '../utils/fecha'
import type { PuestoEpp } from '../services/ssoService'

const KIT_EPP_VACIO: PuestoEpp[] = []

interface Props {
  opened: boolean
  onClose: () => void
}

interface SeleccionKit {
  checked: boolean
  cantidad: number
  nombre: string
}

export function RegistrarEntregaEppModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { registrar, registrarKit } = useEppEntregaMutations()
  const { data: equiposData } = useEquiposProteccion({ estado: true })
  const equipoOptions = (equiposData?.data ?? []).map(e => ({ value: String(e.id), label: `${e.codigo} — ${e.nombre}` }))

  const [modo, setModo] = useState<'individual' | 'kit'>('individual')

  // ── Modo individual (RHF) ──────────────────────────────────────────
  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<EppEntregaFormData>({
    resolver: zodResolver(eppEntregaSchema) as Resolver<EppEntregaFormData>,
    defaultValues: {
      servidor_id: 0,
      equipo_proteccion_id: 0,
      fecha_entrega: '',
      cantidad: 1,
      motivo: 'entrega',
      observaciones: '',
    },
  })

  const onSubmitIndividual = (values: EppEntregaFormData) => {
    registrar.mutateAsync(values).then(handleClose).catch(() => {})
  }

  // ── Modo kit completo ────────────────────────────────────────────
  const [kitServidorId, setKitServidorId] = useState<number | null>(null)
  const [kitFecha, setKitFecha] = useState('')
  const [kitObservaciones, setKitObservaciones] = useState('')
  const [kitSeleccion, setKitSeleccion] = useState<Record<number, SeleccionKit>>({})
  const [equipoExtra, setEquipoExtra] = useState<string | null>(null)

  const { data: kitEquipos = KIT_EPP_VACIO, isLoading: kitLoading } = useKitEppServidor(kitServidorId)

  // Reinicializa la selección cuando llegan nuevos datos del kit (cambio de servidor).
  // Se ajusta durante el render (no en un efecto) siguiendo el patrón de React para
  // derivar estado a partir de un cambio de props/query: https://react.dev/learn/you-might-not-need-an-effect
  const [kitEquiposSincronizados, setKitEquiposSincronizados] = useState(kitEquipos)
  if (kitEquipos !== kitEquiposSincronizados) {
    setKitEquiposSincronizados(kitEquipos)
    const inicial: Record<number, SeleccionKit> = {}
    for (const item of kitEquipos) {
      inicial[item.equipo_proteccion_id] = {
        checked: true,
        cantidad: item.cantidad_requerida,
        nombre: item.equipo_proteccion?.nombre ?? `Equipo ${item.equipo_proteccion_id}`,
      }
    }
    setKitSeleccion(inicial)
  }

  const agregarEquipoExtra = () => {
    if (!equipoExtra) return
    const id = Number(equipoExtra)
    const opcion = equipoOptions.find(o => o.value === equipoExtra)
    setKitSeleccion(prev => ({
      ...prev,
      [id]: { checked: true, cantidad: 1, nombre: opcion?.label ?? `Equipo ${id}` },
    }))
    setEquipoExtra(null)
  }

  const equiposDisponiblesParaAgregar = equipoOptions.filter(o => !(Number(o.value) in kitSeleccion))

  const equiposSeleccionados = Object.entries(kitSeleccion).filter(([, v]) => v.checked)

  const kitValido = !!kitServidorId && !!kitFecha && equiposSeleccionados.length > 0

  const onSubmitKit = () => {
    if (!kitValido) return
    registrarKit.mutateAsync({
      servidor_id: kitServidorId!,
      fecha_entrega: kitFecha,
      observaciones: kitObservaciones || undefined,
      equipos: equiposSeleccionados.map(([id, v]) => ({
        equipo_proteccion_id: Number(id),
        cantidad: v.cantidad,
      })),
    }).then(handleClose).catch(() => {})
  }

  // ── Cierre común ─────────────────────────────────────────────────
  const handleClose = () => {
    reset()
    setModo('individual')
    setKitServidorId(null)
    setKitFecha('')
    setKitObservaciones('')
    setKitSeleccion({})
    setEquipoExtra(null)
    onClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Registrar movimiento de EPP"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="sm">
        <SegmentedControl
          value={modo}
          onChange={(v) => setModo(v as 'individual' | 'kit')}
          data={[
            { label: 'Movimiento individual', value: 'individual' },
            { label: 'Entregar kit completo', value: 'kit' },
          ]}
          fullWidth
        />

        {modo === 'individual' && (
          <form onSubmit={handleSubmit(onSubmitIndividual)}>
            <Stack gap="sm">
              <Controller
                name="servidor_id"
                control={control}
                render={({ field }) => (
                  <BuscarServidorSelect
                    label="Servidor"
                    required
                    value={field.value || null}
                    onChange={(id) => field.onChange(id ?? 0)}
                    error={errors.servidor_id?.message}
                  />
                )}
              />
              <Controller
                name="equipo_proteccion_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Equipo de protección"
                    placeholder="Seleccione un equipo"
                    data={equipoOptions}
                    searchable
                    required
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => field.onChange(v ? Number(v) : 0)}
                    error={errors.equipo_proteccion_id?.message}
                  />
                )}
              />
              <Group grow>
                <Controller
                  name="fecha_entrega"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha"
                      placeholder="Seleccionar"
                      valueFormat="DD/MM/YYYY"
                      required
                      {...contained}
                      value={toDateValue(field.value)}
                      onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                      error={errors.fecha_entrega?.message}
                    />
                  )}
                />
                <Controller
                  name="cantidad"
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label="Cantidad"
                      min={1}
                      {...contained}
                      value={field.value}
                      onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
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
                    data={MOTIVO_ENTREGA_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v as EppEntregaFormData['motivo'])}
                    error={errors.motivo?.message}
                  />
                )}
              />
              <Textarea
                label="Observaciones"
                placeholder="Observaciones (opcional)"
                rows={2}
                {...contained}
                {...register('observaciones')}
                error={errors.observaciones?.message}
              />
              <Group justify="flex-end" mt="md">
                <Button variant="default" onClick={handleClose}>
                  Cancelar
                </Button>
                <Button type="submit" loading={registrar.isPending} color="emerald">
                  Registrar
                </Button>
              </Group>
            </Stack>
          </form>
        )}

        {modo === 'kit' && (
          <Stack gap="sm">
            <BuscarServidorSelect
              label="Servidor"
              required
              value={kitServidorId}
              onChange={setKitServidorId}
            />

            {kitServidorId && kitLoading && (
              <Text size="sm" c="dimmed">Cargando kit del puesto…</Text>
            )}

            {kitServidorId && !kitLoading && kitEquipos.length === 0 && (
              <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
                Este puesto no tiene equipos de protección definidos en su catálogo.
                Puede agregar equipos manualmente abajo.
              </Alert>
            )}

            {kitServidorId && Object.keys(kitSeleccion).length > 0 && (
              <Stack gap={6}>
                <Text size="sm" fw={600}>Equipos a entregar</Text>
                {Object.entries(kitSeleccion).map(([id, item]) => (
                  <Group key={id} wrap="nowrap" align="center">
                    <Checkbox
                      checked={item.checked}
                      onChange={(e) => setKitSeleccion(prev => ({
                        ...prev,
                        [Number(id)]: { ...prev[Number(id)], checked: e.currentTarget.checked },
                      }))}
                      label={item.nombre}
                      style={{ flex: 1 }}
                    />
                    <NumberInput
                      min={1}
                      w={80}
                      disabled={!item.checked}
                      value={item.cantidad}
                      onChange={(v) => setKitSeleccion(prev => ({
                        ...prev,
                        [Number(id)]: { ...prev[Number(id)], cantidad: typeof v === 'number' ? v : 1 },
                      }))}
                    />
                  </Group>
                ))}
              </Stack>
            )}

            {kitServidorId && (
              <Group align="flex-end" wrap="nowrap">
                <Select
                  label="Agregar otro equipo"
                  placeholder="Seleccione un equipo"
                  data={equiposDisponiblesParaAgregar}
                  searchable
                  clearable
                  style={{ flex: 1 }}
                  {...contained}
                  value={equipoExtra}
                  onChange={setEquipoExtra}
                />
                <Button
                  variant="default"
                  leftSection={<IconPlus size={16} />}
                  onClick={agregarEquipoExtra}
                  disabled={!equipoExtra}
                >
                  Agregar
                </Button>
              </Group>
            )}

            <Divider />

            <DatePickerInput
              label="Fecha de entrega"
              placeholder="Seleccionar"
              valueFormat="DD/MM/YYYY"
              required
              {...contained}
              value={toDateValue(kitFecha)}
              onChange={(d) => setKitFecha(fromDateValue(d ?? null))}
            />
            <Textarea
              label="Observaciones"
              placeholder="Observaciones (opcional, aplica a todo el kit)"
              rows={2}
              {...contained}
              value={kitObservaciones}
              onChange={(e) => setKitObservaciones(e.currentTarget.value)}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={handleClose}>
                Cancelar
              </Button>
              <Button
                onClick={onSubmitKit}
                loading={registrarKit.isPending}
                disabled={!kitValido}
                color="emerald"
              >
                Entregar kit ({equiposSeleccionados.length})
              </Button>
            </Group>
          </Stack>
        )}
      </Stack>
    </Modal>
  )
}
