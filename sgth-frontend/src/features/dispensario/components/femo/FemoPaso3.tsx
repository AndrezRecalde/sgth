'use client'

import {
  Stack, Grid, TextInput, Select,
  Textarea, Group, Text, Button,
  Card, ActionIcon, Divider, Modal,
  Badge, Radio,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useDisclosure } from '@mantine/hooks'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconPlus, IconTrash, IconCheck } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarCie10Input } from '../BuscarCie10Input'
import {
  examenSchema, diagnosticoFemoSchema,
  type FichaBaseForm, type ExamenForm,
  type DiagnosticoFemoForm,
} from '../../schemas/femo.schema'
import {
  APTITUD_OPTIONS,
} from '../../services/femoService'
import type { DiagnosticoCie10 } from '../../services/cie10Service'

interface Props {
  fichaData:        Partial<FichaBaseForm>
  examenes:         ExamenForm[]
  diagnosticos:     DiagnosticoFemoForm[]
  onFichaChange:    (data: Partial<FichaBaseForm>) => void
  onExamenesChange: (data: ExamenForm[]) => void
  onDiagnosticosChange: (data: DiagnosticoFemoForm[]) => void
}

const TIPO_EXAMEN_OPTIONS = [
  { value: 'laboratorio', label: 'Laboratorio' },
  { value: 'imagen',      label: 'Imagen'      },
  { value: 'otro',        label: 'Otro'        },
]

const APTITUD_COLORS_UI: Record<string, string> = {
  apto:                   'emerald',
  apto_con_restricciones: 'orange',
  en_observacion:         'blue',
  no_apto:                'red',
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  if (typeof d === 'string') return d.slice(0, 10)
  if (!(d instanceof Date) || isNaN(d.getTime())) return null
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}

export function FemoPaso3({
  fichaData, examenes, diagnosticos,
  onFichaChange, onExamenesChange, onDiagnosticosChange,
}: Props) {
  const contained = useContainedInput()
  const [examenModalOpened,
    { open: abrirExamen, close: cerrarExamen }] = useDisclosure(false)
  const [cie10Sel, setCie10Sel] =
    useState<DiagnosticoCie10 | null>(null)
  const [tipoDiag, setTipoDiag] =
    useState<'presuntivo' | 'definitivo'>('presuntivo')

  const examenForm = useForm<ExamenForm>({
    resolver: zodResolver(examenSchema),
    defaultValues: {
      nombre_examen: '',
      resultado:     null,
      fecha_examen:  null,
      tipo:          'laboratorio',
    },
  })

  const handleAgregarExamen = (values: ExamenForm) => {
    onExamenesChange([...examenes, values])
    examenForm.reset()
    cerrarExamen()
  }

  const handleEliminarExamen = (idx: number) => {
    onExamenesChange(examenes.filter((_, i) => i !== idx))
  }

  const handleAgregarDiagnostico = () => {
    if (!cie10Sel) return
    if (diagnosticos.length >= 6) return
    if (diagnosticos.find(d => d.diagnostico_cie10_id === cie10Sel.id)) return
    onDiagnosticosChange([
      ...diagnosticos,
      {
        diagnostico_cie10_id: cie10Sel.id,
        tipo:                 tipoDiag,
        orden:                diagnosticos.length + 1,
      },
    ])
    setCie10Sel(null)
  }

  const handleEliminarDiagnostico = (idx: number) => {
    onDiagnosticosChange(
      diagnosticos
        .filter((_, i) => i !== idx)
        .map((d, i) => ({ ...d, orden: i + 1 }))
    )
  }

  return (
    <Stack gap="md">
      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase"
            style={{ letterSpacing: '0.05em' }}>
            F. Exámenes complementarios
          </Text>
          <Button
            size="compact-xs"
            variant="subtle"
            leftSection={<IconPlus size={12} />}
            onClick={abrirExamen}
          >
            Agregar
          </Button>
        </Group>

        {examenes.length === 0 ? (
          <Text size="sm" c="dimmed">
            Ningún examen registrado.
          </Text>
        ) : (
          <Stack gap="xs">
            {examenes.map((ex, i) => (
              <Card key={i} withBorder radius="md" p="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Stack gap={0}>
                    <Group gap="xs">
                      <Badge size="xs" variant="light" color="blue">
                        {TIPO_EXAMEN_OPTIONS.find(
                          o => o.value === ex.tipo
                        )?.label}
                      </Badge>
                      <Text size="sm" fw={500}>
                        {ex.nombre_examen}
                      </Text>
                    </Group>
                    {ex.resultado && (
                      <Text size="xs" c="dimmed">{ex.resultado}</Text>
                    )}
                    {ex.fecha_examen && (
                      <Text size="xs" c="dimmed">
                        {new Date(ex.fecha_examen).toLocaleDateString('es-EC')}
                      </Text>
                    )}
                  </Stack>
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    onClick={() => handleEliminarExamen(i)}
                  >
                    <IconTrash size={13} />
                  </ActionIcon>
                </Group>
              </Card>
            ))}
          </Stack>
        )}
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          G. Diagnósticos CIE-10 (máx. 6)
        </Text>

        {diagnosticos.length < 6 && (
          <Grid align="flex-end">
            <Grid.Col span={{ base: 12, md: 7 }}>
              <BuscarCie10Input
                value={cie10Sel}
                onChange={setCie10Sel}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, md: 3 }}>
              <Select
                label="Tipo"
                data={[
                  { value: 'presuntivo', label: 'Presuntivo' },
                  { value: 'definitivo', label: 'Definitivo' },
                ]}
                {...contained}
                value={tipoDiag}
                onChange={(v) =>
                  setTipoDiag((v ?? 'presuntivo') as 'presuntivo' | 'definitivo')
                }
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, md: 2 }}>
              <Button
                fullWidth
                color="emerald"
                leftSection={<IconPlus size={13} />}
                disabled={!cie10Sel}
                onClick={handleAgregarDiagnostico}
              >
                Agregar
              </Button>
            </Grid.Col>
          </Grid>
        )}

        {diagnosticos.length === 0 ? (
          <Text size="sm" c="dimmed">
            Ningún diagnóstico registrado.
          </Text>
        ) : (
          <Stack gap="xs">
            {diagnosticos.map((d, i) => (
              <Card key={i} withBorder radius="md" p="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Group gap="xs">
                    <Text size="xs" c="dimmed">{i + 1}.</Text>
                    <Badge
                      size="sm"
                      variant="light"
                      color={d.tipo === 'definitivo' ? 'emerald' : 'orange'}
                    >
                      {d.tipo}
                    </Badge>
                    <Text size="sm" fw={500}>
                      CIE-10 #{d.diagnostico_cie10_id}
                    </Text>
                  </Group>
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    onClick={() => handleEliminarDiagnostico(i)}
                  >
                    <IconTrash size={13} />
                  </ActionIcon>
                </Group>
              </Card>
            ))}
          </Stack>
        )}
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          H. Aptitud médica para el trabajo
        </Text>
        <Grid>
          {APTITUD_OPTIONS.map((opt) => {
            const isSelected = fichaData.aptitud === opt.value
            return (
              <Grid.Col key={opt.value} span={{ base: 6, md: 3 }}>
                <Card
                  withBorder
                  radius="md"
                  p="sm"
                  style={{
                    borderColor: isSelected
                      ? `var(--mantine-color-${APTITUD_COLORS_UI[opt.value]}-6)`
                      : undefined,
                    borderWidth: isSelected ? 2 : 1,
                    cursor: 'pointer',
                  }}
                  onClick={() => onFichaChange({
                    ...fichaData,
                    aptitud: opt.value as FichaBaseForm['aptitud'],
                  })}
                >
                  <Stack gap={4} align="center">
                    <Radio
                      checked={isSelected}
                      onChange={() => {}}
                      color={APTITUD_COLORS_UI[opt.value]}
                    />
                    <Text
                      size="sm"
                      fw={500}
                      ta="center"
                      c={isSelected
                        ? APTITUD_COLORS_UI[opt.value]
                        : undefined}
                    >
                      {opt.label}
                    </Text>
                  </Stack>
                </Card>
              </Grid.Col>
            )
          })}
        </Grid>

        {(fichaData.aptitud === 'apto_con_restricciones' ||
          fichaData.aptitud === 'no_apto') && (
          <Textarea
            label="Restricciones"
            placeholder="Detalle las restricciones para el puesto de trabajo"
            autosize
            minRows={2}
            {...contained}
            value={fichaData.restricciones ?? ''}
            onChange={(e) => onFichaChange({
              ...fichaData, restricciones: e.currentTarget.value,
            })}
          />
        )}
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          I. Recomendaciones y tratamiento
        </Text>
        <Textarea
          label="Recomendaciones"
          placeholder="Indicaciones médicas para el servidor"
          autosize
          minRows={3}
          {...contained}
          value={fichaData.recomendaciones ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, recomendaciones: e.currentTarget.value,
          })}
        />
        <Textarea
          label="Tratamiento"
          placeholder="Tratamiento indicado si aplica"
          autosize
          minRows={2}
          {...contained}
          value={fichaData.tratamiento ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, tratamiento: e.currentTarget.value,
          })}
        />
        <Textarea
          label="Observaciones generales"
          autosize
          minRows={2}
          {...contained}
          value={fichaData.observaciones ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, observaciones: e.currentTarget.value,
          })}
        />
      </Stack>

      <Modal
        opened={examenModalOpened}
        onClose={cerrarExamen}
        title="Agregar examen complementario"
        size="md"
        radius="xl"
      >
        <form onSubmit={examenForm.handleSubmit(handleAgregarExamen)}>
          <Stack gap="sm">
            <Controller
              name="tipo"
              control={examenForm.control}
              render={({ field }) => (
                <Select
                  label="Tipo de examen"
                  data={TIPO_EXAMEN_OPTIONS}
                  required
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v ?? 'laboratorio')}
                />
              )}
            />
            <TextInput
              label="Nombre del examen"
              required
              placeholder="Ej: Hemograma completo"
              {...contained}
              {...examenForm.register('nombre_examen')}
              error={examenForm.formState.errors.nombre_examen?.message}
            />
            <Textarea
              label="Resultado"
              autosize
              minRows={2}
              {...contained}
              {...examenForm.register('resultado')}
            />
            <DatePickerInput
              label="Fecha del examen"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDate(examenForm.watch('fecha_examen'))}
              onChange={(d) =>
                examenForm.setValue('fecha_examen', fromDate(d as Date | null))
              }
            />
            <Group justify="flex-end" mt="sm">
              <Button variant="default" onClick={cerrarExamen}>
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                leftSection={<IconCheck size={14} />}
              >
                Agregar
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>
    </Stack>
  )
}
