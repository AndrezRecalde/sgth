'use client'

import React from 'react'
import {
  Modal, Button, Group, Stack, Select, Textarea, TextInput, Alert,
  Collapse, Switch, Divider, UnstyledButton, Text,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, useWatch, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQueryClient, useMutation } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX, IconInfoCircle } from '@tabler/icons-react'
import { expedienteService } from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import {
  movimientoSchema, type MovimientoFormData,
} from '../schemas/movimiento.schema'
import {
  accionesElegibles, ACCION_PERSONAL_LABELS, type AccionPersonalTipo,
} from '../utils/nombramiento'

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  tipoNombramiento?: string | null
}

const BLANK_VALUES: MovimientoFormData = {
  tipo_movimiento: 'cambio_administrativo',
  descripcion:     '',
  fecha_efectiva:  '',
  fecha_inicio:    null,
  fecha_fin:       null,
  resolucion_numero: '',
  observacion:     '',
  codigo:          '',
  lugar_trabajo:   '',
  caucionado:      false,
  caucion_numero:  '',
  caucion_fecha:   null,
}

export function MovimientoModal({ opened, onClose, servidorId, tipoNombramiento }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const qc = useQueryClient()

  const opciones = accionesElegibles(tipoNombramiento)
  const tipoOptions = opciones.map((v) => ({ value: v, label: ACCION_PERSONAL_LABELS[v] }))

  const {
    control, handleSubmit, reset, register,
    formState: { errors, isSubmitting },
  } = useForm<MovimientoFormData>({
    resolver: zodResolver(movimientoSchema),
    defaultValues: { ...BLANK_VALUES, tipo_movimiento: opciones[0] ?? 'cambio_administrativo' },
  })

  const tipoSeleccionado = useWatch({ control, name: 'tipo_movimiento' })
  const esComisionSinRemuneracion = tipoSeleccionado === 'comision_sin_remuneracion'
  const caucionadoSeleccionado = useWatch({ control, name: 'caucionado' })

  const [pdfOpened, { toggle: togglePdf, close: closePdf }] = useDisclosure(false)

  React.useEffect(() => {
    if (opened) {
      reset({ ...BLANK_VALUES, tipo_movimiento: opciones[0] ?? 'cambio_administrativo' })
    } else {
      closePdf()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened])

  const handleClose = () => {
    reset(BLANK_VALUES)
    onClose()
  }

  const crear = useMutation({
    mutationFn: (data: MovimientoFormData) =>
      expedienteService.crearMovimiento(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Acción de personal registrada',
        message: 'El movimiento fue registrado en el historial del servidor.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['movimientos', servidorId] })
      handleClose()
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo registrar',
        message: getApiErrorMessage(error, 'No se pudo registrar la acción de personal.'),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

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

  const onSubmit = (values: MovimientoFormData) => crear.mutate(values)

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Registrar acción de personal"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      {opciones.length === 0 ? (
        <Alert icon={<IconInfoCircle size={16} />} color="yellow" variant="light">
          El servidor no tiene un contrato vigente elegible para ninguna acción de
          personal formal, o no tiene contrato vigente registrado.
        </Alert>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="sm">
            <Controller
              name="tipo_movimiento"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de acción de personal"
                  data={tipoOptions}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange((v ?? opciones[0]) as AccionPersonalTipo)}
                  error={errors.tipo_movimiento?.message}
                />
              )}
            />

            <Textarea
              label="Descripción"
              placeholder="Detalle de la acción de personal"
              minRows={2}
              {...contained}
              {...register('descripcion')}
              error={errors.descripcion?.message}
            />

            <Controller
              name="fecha_efectiva"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha efectiva"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d) ?? '')}
                  error={errors.fecha_efectiva?.message}
                />
              )}
            />

            {esComisionSinRemuneracion && (
              <>
                <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
                  La comisión de servicios sin remuneración debe durar entre 1 y 6 años,
                  y el servidor debe tener más de 2 años de antigüedad en la institución.
                </Alert>
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
                      onChange={(d) => field.onChange(fromDate(d))}
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
                      onChange={(d) => field.onChange(fromDate(d))}
                      error={errors.fecha_fin?.message}
                    />
                  )}
                />
              </>
            )}

            <TextInput
              label="Número de resolución"
              placeholder="Ej: RES-2026-001 (opcional)"
              {...contained}
              {...register('resolucion_numero')}
              error={errors.resolucion_numero?.message}
            />

            <Textarea
              label="Observación"
              placeholder="Observación adicional (opcional)"
              minRows={2}
              {...contained}
              {...register('observacion')}
              error={errors.observacion?.message}
            />

            <Divider my={4} />
            <UnstyledButton onClick={togglePdf}>
              <Group gap={4}>
                {pdfOpened ? <IconChevronDown size={14} /> : <IconChevronRight size={14} />}
                <Text size="sm" fw={500}>
                  Datos para el PDF de Acción de Personal (opcional)
                </Text>
              </Group>
            </UnstyledButton>
            <Collapse expanded={pdfOpened}>
              <Stack gap="sm" mt="xs">
                <TextInput
                  label="Código de la acción"
                  placeholder="Ej: CA-2026-001 (opcional)"
                  {...contained}
                  {...register('codigo')}
                  error={errors.codigo?.message}
                />
                <TextInput
                  label="Lugar de trabajo"
                  placeholder="Ej: Esmeraldas (opcional)"
                  {...contained}
                  {...register('lugar_trabajo')}
                  error={errors.lugar_trabajo?.message}
                />
                <Controller
                  name="caucionado"
                  control={control}
                  render={({ field }) => (
                    <Switch
                      label="¿Caucionado?"
                      checked={field.value ?? false}
                      onChange={(e) => field.onChange(e.currentTarget.checked)}
                      color="emerald"
                    />
                  )}
                />
                {caucionadoSeleccionado && (
                  <>
                    <TextInput
                      label="Caución registrada con N°"
                      placeholder="Número de caución"
                      {...contained}
                      {...register('caucion_numero')}
                      error={errors.caucion_numero?.message}
                    />
                    <Controller
                      name="caucion_fecha"
                      control={control}
                      render={({ field }) => (
                        <DatePickerInput
                          label="Fecha de caución"
                          placeholder="Seleccionar fecha"
                          valueFormat="YYYY-MM-DD"
                          {...contained}
                          value={toDate(field.value)}
                          onChange={(d) => field.onChange(fromDate(d))}
                          error={errors.caucion_fecha?.message}
                        />
                      )}
                    />
                  </>
                )}
              </Stack>
            </Collapse>

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={handleClose}>Cancelar</Button>
              <Button type="submit" color="emerald" variant="light" loading={isSubmitting}>
                Registrar
              </Button>
            </Group>
          </Stack>
        </form>
      )}
    </Modal>
  )
}
