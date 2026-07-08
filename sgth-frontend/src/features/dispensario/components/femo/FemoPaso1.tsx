'use client'

import {
  Stack, Grid, TextInput, Select,
  Checkbox, Group, Text, NumberInput,
  Button, Card, ActionIcon, Divider,
  Modal,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useDisclosure } from '@mantine/hooks'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconPlus, IconTrash, IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import {
  fichaBaseSchema, antecedenteSchema,
  type FichaBaseForm, type AntecedenteForm,
} from '../../schemas/femo.schema'
import { TIPO_FICHA_OPTIONS } from '../../services/femoService'

interface Props {
  fichaData:          Partial<FichaBaseForm>
  constantesData:     Record<string, number | null>
  antecedentes:       AntecedenteForm[]
  onFichaChange:      (data: Partial<FichaBaseForm>) => void
  onConstantesChange: (data: Record<string, number | null>) => void
  onAntecedentesChange: (data: AntecedenteForm[]) => void
}

const TIPO_ANTECEDENTE_OPTIONS = [
  { value: 'clinico',               label: 'Clínico'              },
  { value: 'quirurgico',            label: 'Quirúrgico'           },
  { value: 'familiar',              label: 'Familiar'             },
  { value: 'ginecologico',          label: 'Ginecológico'         },
  { value: 'reproductivo_masculino',label: 'Reproductivo masc.'   },
  { value: 'otro',                  label: 'Otro'                 },
]

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
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

export function FemoPaso1({
  fichaData, constantesData, antecedentes,
  onFichaChange, onConstantesChange, onAntecedentesChange,
}: Props) {
  const contained = useContainedInput()
  const [antModalOpened,
    { open: abrirAntModal, close: cerrarAntModal }] = useDisclosure(false)

  const antForm = useForm<AntecedenteForm>({
    resolver: zodResolver(antecedenteSchema),
    defaultValues: { tipo: '', descripcion: '', fecha_aproximada: null },
  })

  const handleAgregarAntecedente = (values: AntecedenteForm) => {
    onAntecedentesChange([...antecedentes, values])
    antForm.reset()
    cerrarAntModal()
  }

  const handleEliminarAntecedente = (idx: number) => {
    onAntecedentesChange(antecedentes.filter((_, i) => i !== idx))
  }

  const handleConstante = (campo: string, valor: number | null) => {
    const nuevas = { ...constantesData, [campo]: valor }
    if (nuevas.peso_kg && nuevas.talla_cm) {
      const imc = nuevas.peso_kg / Math.pow(nuevas.talla_cm / 100, 2)
      nuevas.imc = Math.round(imc * 100) / 100
    }
    onConstantesChange(nuevas)
  }

  return (
    <Stack gap="md">
      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          A. Datos generales
        </Text>
        <Grid>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <BuscarServidorSelect
              label="Servidor"
              required
              value={fichaData.servidor_id ?? null}
              onChange={(id) => onFichaChange({ ...fichaData, servidor_id: id ?? undefined })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <DatePickerInput
              label="Fecha de evaluación"
              valueFormat="DD/MM/YYYY"
              required
              {...contained}
              value={toDate(fichaData.fecha_evaluacion)}
              onChange={(d) => onFichaChange({
                ...fichaData,
                fecha_evaluacion: fromDate(d as Date | null) ?? '',
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <Select
              label="Tipo de evaluación"
              data={TIPO_FICHA_OPTIONS}
              required
              {...contained}
              value={fichaData.tipo_ficha ?? null}
              onChange={(v) => onFichaChange({
                ...fichaData, tipo_ficha: v as FichaBaseForm['tipo_ficha'],
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <TextInput
              label="Puesto de trabajo"
              placeholder="Ej: Técnico de campo"
              {...contained}
              value={fichaData.puesto_trabajo ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, puesto_trabajo: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={12}>
            <Group gap="md">
              <Checkbox
                label="Embarazada"
                checked={fichaData.grupo_embarazada ?? false}
                onChange={(e) => onFichaChange({
                  ...fichaData,
                  grupo_embarazada: e.currentTarget.checked,
                })}
              />
              <Checkbox
                label="Discapacidad"
                checked={fichaData.grupo_discapacidad ?? false}
                onChange={(e) => onFichaChange({
                  ...fichaData,
                  grupo_discapacidad: e.currentTarget.checked,
                })}
              />
            </Group>
          </Grid.Col>
        </Grid>
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          B. Signos vitales y antropometría
        </Text>
        <Grid>
          {[
            { campo: 'peso_kg',              label: 'Peso (kg)',      placeholder: '0.0'  },
            { campo: 'talla_cm',             label: 'Talla (cm)',     placeholder: '0'    },
            { campo: 'imc',                  label: 'IMC',            placeholder: 'Auto', readonly: true },
            { campo: 'temperatura_c',        label: 'Temp. (°C)',     placeholder: '37.0' },
            { campo: 'presion_sistolica',     label: 'P. Sistólica',   placeholder: '120'  },
            { campo: 'presion_diastolica',    label: 'P. Diastólica',  placeholder: '80'   },
            { campo: 'frecuencia_cardiaca',   label: 'F. Cardíaca',    placeholder: 'bpm'  },
            { campo: 'frecuencia_respiratoria',label: 'F. Respiratoria',placeholder: 'rpm' },
            { campo: 'saturacion_oxigeno',    label: 'Sat. O2 (%)',    placeholder: '98'   },
            { campo: 'glucosa',               label: 'Glucosa',        placeholder: 'mg/dL'},
          ].map(({ campo, label, placeholder, readonly }) => (
            <Grid.Col key={campo} span={{ base: 6, md: 3 }}>
              <NumberInput
                label={label}
                placeholder={placeholder}
                size="sm"
                {...contained}
                readOnly={readonly}
                value={constantesData[campo] ?? undefined}
                onChange={(v) => !readonly && handleConstante(
                  campo, v !== '' ? Number(v) : null
                )}
              />
            </Grid.Col>
          ))}
        </Grid>
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase"
            style={{ letterSpacing: '0.05em' }}>
            C. Antecedentes personales
          </Text>
          <Button
            size="compact-xs"
            variant="subtle"
            leftSection={<IconPlus size={12} />}
            onClick={abrirAntModal}
          >
            Agregar
          </Button>
        </Group>

        {antecedentes.length === 0 ? (
          <Text size="sm" c="dimmed">
            Ningún antecedente registrado.
          </Text>
        ) : (
          <Stack gap="xs">
            {antecedentes.map((a, i) => (
              <Card key={i} withBorder radius="md" p="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Stack gap={0}>
                    <Text size="sm" fw={500}>
                      {TIPO_ANTECEDENTE_OPTIONS.find(
                        o => o.value === a.tipo
                      )?.label ?? a.tipo}
                      {a.fecha_aproximada && ` — ${a.fecha_aproximada}`}
                    </Text>
                    <Text size="xs" c="dimmed">{a.descripcion}</Text>
                  </Stack>
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    onClick={() => handleEliminarAntecedente(i)}
                  >
                    <IconTrash size={13} />
                  </ActionIcon>
                </Group>
              </Card>
            ))}
          </Stack>
        )}
      </Stack>

      <Modal
        opened={antModalOpened}
        onClose={cerrarAntModal}
        title="Agregar antecedente"
        size="sm"
        radius="xl"
      >
        <form onSubmit={antForm.handleSubmit(handleAgregarAntecedente)}>
          <Stack gap="sm">
            <Controller
              name="tipo"
              control={antForm.control}
              render={({ field }) => (
                <Select
                  label="Tipo"
                  data={TIPO_ANTECEDENTE_OPTIONS}
                  required
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v ?? '')}
                  error={antForm.formState.errors.tipo?.message}
                />
              )}
            />
            <TextInput
              label="Descripción"
              required
              {...contained}
              {...antForm.register('descripcion')}
              error={antForm.formState.errors.descripcion?.message}
            />
            <Controller
              name="fecha_aproximada"
              control={antForm.control}
              render={({ field }) => (
                <NumberInput
                  label="Año aproximado (opcional)"
                  placeholder="Ej: 2018"
                  min={1900}
                  max={new Date().getFullYear()}
                  {...contained}
                  value={field.value ?? undefined}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                />
              )}
            />
            <Group justify="flex-end" mt="sm">
              <Button variant="default" onClick={cerrarAntModal}>
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
