'use client'

import {
  Stack,
  Grid,
  TextInput,
  Group,
  Text,
  Button,
  Card,
  ActionIcon,
  Badge,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  type FichaBaseForm, type FactorRiesgoForm,
  type ActividadRiesgoForm, type EmpleoAnteriorForm,
} from '../../schemas/femo.schema'
import { FemoEmpleoAnteriorModal } from './FemoEmpleoAnteriorModal'
import { FemoMatrizRiesgos } from './FemoMatrizRiesgos'
import { TIPO_EVENTO_LABORAL_OPTIONS } from '../../services/femoOptions'
import { FemoSeccion } from './FemoSeccion'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

interface Props {
  fichaData:            Partial<FichaBaseForm>
  puestoId:             number | null
  actividadesRiesgo:    ActividadRiesgoForm[]
  factoresRiesgo:       FactorRiesgoForm[]
  empleosAnteriores:    EmpleoAnteriorForm[]
  onFichaChange:        (data: Partial<FichaBaseForm>) => void
  onActividadesChange:  (data: ActividadRiesgoForm[]) => void
  onFactoresChange:     (data: FactorRiesgoForm[]) => void
  onEmpleosChange:      (data: EmpleoAnteriorForm[]) => void
}

export function FemoPaso2({
  fichaData, puestoId, actividadesRiesgo, factoresRiesgo, empleosAnteriores,
  onFichaChange, onActividadesChange, onFactoresChange, onEmpleosChange,
}: Props) {
  const contained = useContainedInput()
  const [empleoModalOpened,
    { open: abrirEmpleo, close: cerrarEmpleo }] = useDisclosure(false)

  const handleEliminarEmpleo = (idx: number) => {
    onEmpleosChange(empleosAnteriores.filter((_, i) => i !== idx))
  }

  return (
    <Stack gap="xl">
      <FemoMatrizRiesgos
        puestoId={puestoId}
        actividadesRiesgo={actividadesRiesgo}
        factoresRiesgo={factoresRiesgo}
        onActividadesChange={onActividadesChange}
        onFactoresChange={onFactoresChange}
      />

      <FemoSeccion
        letra="H"
        titulo="Actividad laboral, incidentes y accidentes"
        accion={
          <Button
            size="xs"
            variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={abrirEmpleo}
          >
            Agregar empleo
          </Button>
        }
      >

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
                    <Group gap="xs">
                      <Text size="sm" fw={500}>
                        {emp.centro_trabajo}
                      </Text>
                      {emp.tipo_evento_laboral && emp.tipo_evento_laboral !== 'ninguno' && (
                        <Badge size="xs" variant="light" color="red">
                          {TIPO_EVENTO_LABORAL_OPTIONS.find(
                            o => o.value === emp.tipo_evento_laboral
                          )?.label}
                        </Badge>
                      )}
                    </Group>
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
      </FemoSeccion>

      <FemoSeccion letra="I" titulo="Actividades extra laborales">
        <Grid>
          <Grid.Col span={{ base: 12, md: 8 }}>
            <TextInput
              label="Descripción"
              {...contained}
              value={fichaData.actividad_extralaboral_descripcion ?? ''}
              onChange={(e) => onFichaChange({
                ...fichaData, actividad_extralaboral_descripcion: e.currentTarget.value,
              })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 4 }}>
            <DatePickerInput
              label="Fecha"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDateValue(fichaData.actividad_extralaboral_fecha)}
              onChange={(d) => onFichaChange({
                ...fichaData, actividad_extralaboral_fecha: fromDateValueOrNull(d as Date | null),
              })}
            />
          </Grid.Col>
        </Grid>
      </FemoSeccion>

      <FemoEmpleoAnteriorModal
        opened={empleoModalOpened}
        onClose={cerrarEmpleo}
        onAgregar={(values) => onEmpleosChange([...empleosAnteriores, values])}
      />
    </Stack>
  )
}
