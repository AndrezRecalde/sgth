'use client'

import {
  Stack, Grid, TextInput, Checkbox,
  Group, Text, Button, Card,
  ActionIcon, Divider, Modal,
  Badge, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useDisclosure } from '@mantine/hooks'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconPlus, IconTrash, IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  empleoAnteriorSchema,
  type FactorRiesgoForm,
  type EmpleoAnteriorForm,
} from '../../schemas/femo.schema'

interface Props {
  factoresRiesgo:       FactorRiesgoForm[]
  empleosAnteriores:    EmpleoAnteriorForm[]
  onFactoresChange:     (data: FactorRiesgoForm[]) => void
  onEmpleosChange:      (data: EmpleoAnteriorForm[]) => void
}

const CATEGORIAS_RIESGO: {
  key:     string
  label:   string
  color:   string
  factores: string[]
}[] = [
  {
    key:   'fisico',
    label: 'Físico',
    color: 'blue',
    factores: [
      'Ruido', 'Vibración', 'Iluminación deficiente',
      'Temperatura extrema', 'Radiación ionizante',
      'Radiación no ionizante', 'Presión anormal',
    ],
  },
  {
    key:   'seguridad',
    label: 'Seguridad',
    color: 'orange',
    factores: [
      'Locativos', 'Mecánicos', 'Eléctricos',
      'Trabajo en alturas', 'Espacios confinados',
    ],
  },
  {
    key:   'quimico',
    label: 'Químico',
    color: 'grape',
    factores: [
      'Polvos', 'Humos', 'Gases y vapores',
      'Líquidos', 'Aerosoles',
    ],
  },
  {
    key:   'biologico',
    label: 'Biológico',
    color: 'teal',
    factores: [
      'Virus', 'Bacterias', 'Hongos',
      'Parásitos', 'Fluidos corporales',
    ],
  },
  {
    key:   'ergonomico',
    label: 'Ergonómico',
    color: 'yellow',
    factores: [
      'Posturas forzadas', 'Carga física',
      'Movimientos repetitivos', 'PVD',
      'Trabajo de pie prolongado',
    ],
  },
  {
    key:   'psicosocial',
    label: 'Psicosocial',
    color: 'red',
    factores: [
      'Monotonía', 'Sobrecarga laboral',
      'Relaciones interpersonales', 'Trabajo nocturno',
      'Alta responsabilidad',
    ],
  },
]

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

export function FemoPaso2({
  factoresRiesgo, empleosAnteriores,
  onFactoresChange, onEmpleosChange,
}: Props) {
  const contained = useContainedInput()
  const [empleoModalOpened,
    { open: abrirEmpleo, close: cerrarEmpleo }] = useDisclosure(false)

  const empleoForm = useForm<EmpleoAnteriorForm>({
    resolver: zodResolver(empleoAnteriorSchema),
    defaultValues: {
      centro_trabajo: '',
      actividades_desempenadas: '',
      fecha_inicio: null,
      fecha_fin:    null,
      observaciones: null,
    },
  })

  const isFactorActivo = (categoria: string, factor: string) =>
    factoresRiesgo.some(
      f => f.categoria === categoria && f.factor === factor && f.presente
    )

  const toggleFactor = (categoria: string, factor: string) => {
    const existe = factoresRiesgo.find(
      f => f.categoria === categoria && f.factor === factor
    )
    if (existe) {
      onFactoresChange(
        factoresRiesgo.filter(
          f => !(f.categoria === categoria && f.factor === factor)
        )
      )
    } else {
      onFactoresChange([
        ...factoresRiesgo,
        { categoria, factor, presente: true, medida_preventiva: null },
      ])
    }
  }

  const handleAgregarEmpleo = (values: EmpleoAnteriorForm) => {
    onEmpleosChange([...empleosAnteriores, values])
    empleoForm.reset()
    cerrarEmpleo()
  }

  const handleEliminarEmpleo = (idx: number) => {
    onEmpleosChange(empleosAnteriores.filter((_, i) => i !== idx))
  }

  return (
    <Stack gap="md">
      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          D. Factores de riesgo laboral
        </Text>
        <Stack gap="sm">
          {CATEGORIAS_RIESGO.map((cat) => {
            const activos = factoresRiesgo.filter(
              f => f.categoria === cat.key
            ).length
            return (
              <Card key={cat.key} withBorder radius="md" p="sm">
                <Group justify="space-between" mb="xs">
                  <Group gap="xs">
                    <Badge
                      size="sm"
                      variant="light"
                      color={cat.color}
                    >
                      {cat.label}
                    </Badge>
                    {activos > 0 && (
                      <Text size="xs" c="dimmed">
                        {activos} seleccionado{activos !== 1 ? 's' : ''}
                      </Text>
                    )}
                  </Group>
                </Group>
                <Group gap="sm" wrap="wrap">
                  {cat.factores.map((factor) => (
                    <Checkbox
                      key={factor}
                      label={factor}
                      size="sm"
                      checked={isFactorActivo(cat.key, factor)}
                      onChange={() => toggleFactor(cat.key, factor)}
                    />
                  ))}
                </Group>
              </Card>
            )
          })}
        </Stack>
      </Stack>

      <Divider />

      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase"
            style={{ letterSpacing: '0.05em' }}>
            E. Empleos anteriores
          </Text>
          <Button
            size="compact-xs"
            variant="subtle"
            leftSection={<IconPlus size={12} />}
            onClick={abrirEmpleo}
          >
            Agregar
          </Button>
        </Group>

        {empleosAnteriores.length === 0 ? (
          <Text size="sm" c="dimmed">
            Ningún empleo anterior registrado.
          </Text>
        ) : (
          <Stack gap="xs">
            {empleosAnteriores.map((emp, i) => (
              <Card key={i} withBorder radius="md" p="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Stack gap={0}>
                    <Text size="sm" fw={500}>
                      {emp.centro_trabajo}
                    </Text>
                    {emp.actividades_desempenadas && (
                      <Text size="xs" c="dimmed">
                        {emp.actividades_desempenadas}
                      </Text>
                    )}
                    {(emp.fecha_inicio || emp.fecha_fin) && (
                      <Text size="xs" c="dimmed">
                        {emp.fecha_inicio ?? '?'} — {emp.fecha_fin ?? 'presente'}
                      </Text>
                    )}
                  </Stack>
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    onClick={() => handleEliminarEmpleo(i)}
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
        opened={empleoModalOpened}
        onClose={cerrarEmpleo}
        title="Agregar empleo anterior"
        size="md"
        radius="xl"
      >
        <form onSubmit={empleoForm.handleSubmit(handleAgregarEmpleo)}>
          <Stack gap="sm">
            <TextInput
              label="Centro de trabajo"
              required
              {...contained}
              {...empleoForm.register('centro_trabajo')}
              error={empleoForm.formState.errors.centro_trabajo?.message}
            />
            <Textarea
              label="Actividades desempeñadas"
              autosize
              minRows={2}
              {...contained}
              {...empleoForm.register('actividades_desempenadas')}
            />
            <Grid>
              <Grid.Col span={6}>
                <DatePickerInput
                  label="Fecha inicio"
                  valueFormat="DD/MM/YYYY"
                  clearable
                  {...contained}
                  value={toDate(empleoForm.watch('fecha_inicio'))}
                  onChange={(d) =>
                    empleoForm.setValue('fecha_inicio', fromDate(d as Date | null))
                  }
                />
              </Grid.Col>
              <Grid.Col span={6}>
                <DatePickerInput
                  label="Fecha fin"
                  valueFormat="DD/MM/YYYY"
                  clearable
                  {...contained}
                  value={toDate(empleoForm.watch('fecha_fin'))}
                  onChange={(d) =>
                    empleoForm.setValue('fecha_fin', fromDate(d as Date | null))
                  }
                />
              </Grid.Col>
            </Grid>
            <Textarea
              label="Observaciones"
              autosize
              minRows={2}
              {...contained}
              {...empleoForm.register('observaciones')}
            />
            <Group justify="flex-end" mt="sm">
              <Button variant="default" onClick={cerrarEmpleo}>
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
