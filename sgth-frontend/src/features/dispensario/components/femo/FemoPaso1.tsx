'use client'

import {
  Stack, Grid, TextInput, Select,
  Checkbox, Group, Text,
  Button, Card, ActionIcon, Divider,
  Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  type FichaBaseForm, type AntecedenteForm,
  type AntecedenteReproductivoForm, type ConsumoSustanciaForm,
} from '../../schemas/femo.schema'
import { TIPO_FICHA_OPTIONS, TIPO_ANTECEDENTE_OPTIONS } from '../../services/femoOptions'
import { FemoAntecedenteModal } from './FemoAntecedenteModal'
import { FemoAntecedentesReproductivosSection } from './FemoAntecedentesReproductivosSection'
import { FemoConsumoSustanciasTable } from './FemoConsumoSustanciasTable'

interface Props {
  fichaData:          Partial<FichaBaseForm>
  constantesData:     Record<string, number | null>
  antecedentes:       AntecedenteForm[]
  antecedenteReproductivo: Partial<AntecedenteReproductivoForm>
  consumoSustancias:  ConsumoSustanciaForm[]
  onFichaChange:      (data: Partial<FichaBaseForm>) => void
  onAntecedentesChange: (data: AntecedenteForm[]) => void
  onAntecedenteReproductivoChange: (data: Partial<AntecedenteReproductivoForm>) => void
  onConsumoSustanciasChange: (data: ConsumoSustanciaForm[]) => void
}

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
  antecedenteReproductivo, consumoSustancias,
  onFichaChange, onAntecedentesChange,
  onAntecedenteReproductivoChange, onConsumoSustanciasChange,
}: Props) {
  const contained = useContainedInput()
  const [antModalOpened,
    { open: abrirAntModal, close: cerrarAntModal }] = useDisclosure(false)

  const handleEliminarAntecedente = (idx: number) => {
    onAntecedentesChange(antecedentes.filter((_, i) => i !== idx))
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
          <Grid.Col span={{ base: 12, md: 4 }}>
            <TextInput
              label="Puesto de trabajo"
              placeholder="Ej: Técnico de campo"
              description="Prellenado según convocatoria o expediente — editable"
              {...contained}
              value={fichaData.puesto_trabajo ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, puesto_trabajo: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 3 }}>
            <TextInput
              label="Puesto (CIUO)"
              {...contained}
              value={fichaData.puesto_trabajo_ciuo ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, puesto_trabajo_ciuo: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 5 }}>
            <DatePickerInput
              label="Fecha de ingreso al trabajo"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDate(fichaData.fecha_ingreso_trabajo)}
              onChange={(d) => onFichaChange({
                ...fichaData, fecha_ingreso_trabajo: fromDate(d as Date | null),
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
              {fichaData.grupo_discapacidad && (
                <TextInput
                  label="Porcentaje"
                  placeholder="%"
                  size="xs"
                  w={100}
                  {...contained}
                  value={fichaData.porcentaje_discapacidad ?? ''}
                  onChange={(e) => onFichaChange({
                    ...fichaData, porcentaje_discapacidad: e.currentTarget.value,
                  })}
                />
              )}
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
        {Object.values(constantesData).every(v => v === null || v === undefined) ? (
          <Text size="sm" c="orange">
            Aún no hay signos vitales registrados por Enfermería para esta solicitud.
          </Text>
        ) : (
          <>
            <Text size="xs" c="dimmed">
              Registrados por Enfermería (Atención SSO) — solo lectura.
            </Text>
            <Grid>
              {[
                { campo: 'peso_kg',               label: 'Peso (kg)' },
                { campo: 'talla_cm',              label: 'Talla (cm)' },
                { campo: 'imc',                   label: 'IMC' },
                { campo: 'temperatura_c',         label: 'Temp. (°C)' },
                { campo: 'presion_sistolica',      label: 'P. Sistólica' },
                { campo: 'presion_diastolica',     label: 'P. Diastólica' },
                { campo: 'frecuencia_cardiaca',    label: 'F. Cardíaca' },
                { campo: 'frecuencia_respiratoria', label: 'F. Respiratoria' },
                { campo: 'saturacion_oxigeno',     label: 'Sat. O2 (%)' },
                { campo: 'glucosa',                label: 'Glucosa' },
              ].map(({ campo, label }) => (
                <Grid.Col key={campo} span={{ base: 6, md: 3 }}>
                  <Text size="xs" c="dimmed">{label}</Text>
                  <Text size="sm" fw={500}>
                    {constantesData[campo] ?? '—'}
                  </Text>
                </Grid.Col>
              ))}
            </Grid>
          </>
        )}
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

        <Textarea
          label="Enfermedad o problema actual"
          autosize
          minRows={2}
          {...contained}
          value={fichaData.enfermedad_actual ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, enfermedad_actual: e.currentTarget.value,
          })}
        />

        <FemoAntecedentesReproductivosSection
          data={antecedenteReproductivo}
          onChange={onAntecedenteReproductivoChange}
        />

        <FemoConsumoSustanciasTable
          data={consumoSustancias}
          onChange={onConsumoSustanciasChange}
        />

        <Grid>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <TextInput
              label="Actividad física (¿cuál?)"
              {...contained}
              value={fichaData.actividad_fisica_cual ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, actividad_fisica_cual: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <TextInput
              label="Tiempo de actividad física"
              {...contained}
              value={fichaData.actividad_fisica_tiempo ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, actividad_fisica_tiempo: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <TextInput
              label="Medicación habitual (¿cuál?)"
              {...contained}
              value={fichaData.medicacion_habitual_cual ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, medicacion_habitual_cual: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <TextInput
              label="Cantidad"
              {...contained}
              value={fichaData.medicacion_habitual_cantidad ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, medicacion_habitual_cantidad: e.currentTarget.value,
              })}
            />
          </Grid.Col>
        </Grid>
      </Stack>

      <FemoAntecedenteModal
        opened={antModalOpened}
        onClose={cerrarAntModal}
        onAgregar={(values) => onAntecedentesChange([...antecedentes, values])}
      />
    </Stack>
  )
}
