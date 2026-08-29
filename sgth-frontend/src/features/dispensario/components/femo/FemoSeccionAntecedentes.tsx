'use client'

import { ActionIcon, Button, Card, Grid, Group, Stack, Text, TextInput } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { TIPO_ANTECEDENTE_OPTIONS } from '../../services/femoOptions'
import type {
  AntecedenteForm, AntecedenteReproductivoForm,
  ConsumoSustanciaForm, FichaBaseForm,
} from '../../schemas/femo.schema'
import type { SexoPaciente } from '../../services/solicitudCertificacionService'
import { FemoSeccion } from './FemoSeccion'
import { SiNoSinRespuesta } from './SiNoSinRespuesta'
import { FemoAntecedenteModal } from './FemoAntecedenteModal'
import { FemoAntecedentesReproductivosSection } from './FemoAntecedentesReproductivosSection'
import { FemoConsumoSustanciasTable } from './FemoConsumoSustanciasTable'

interface Props {
  fichaData: Partial<FichaBaseForm>
  antecedentes: AntecedenteForm[]
  antecedenteReproductivo: Partial<AntecedenteReproductivoForm>
  consumoSustancias: ConsumoSustanciaForm[]
  onFichaChange: (data: Partial<FichaBaseForm>) => void
  onAntecedentesChange: (data: AntecedenteForm[]) => void
  onAntecedenteReproductivoChange: (data: Partial<AntecedenteReproductivoForm>) => void
  onConsumoSustanciasChange: (data: ConsumoSustanciaForm[]) => void
  /** Sexo del paciente, para elegir el bloque reproductivo del MSP. */
  sexo?: SexoPaciente
}

/** Sección C del formulario 028, con sus tres sub-bloques del MSP. */
export function FemoSeccionAntecedentes({
  fichaData, antecedentes, antecedenteReproductivo, consumoSustancias,
  onFichaChange, onAntecedentesChange,
  onAntecedenteReproductivoChange, onConsumoSustanciasChange, sexo,
}: Props) {
  const contained = useContainedInput()
  const [modalAbierto, modal] = useDisclosure(false)

  const set = (cambios: Partial<FichaBaseForm>) =>
    onFichaChange({ ...fichaData, ...cambios })

  const eliminar = (idx: number) =>
    onAntecedentesChange(antecedentes.filter((_, i) => i !== idx))

  return (
    <FemoSeccion
      letra="C"
      titulo="Antecedentes personales"
      accion={
        <Button
          size="xs"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={modal.open}
        >
          Agregar antecedente
        </Button>
      }
    >
      {antecedentes.length === 0 ? (
        <Text size="sm" c="dimmed">
          Ningún antecedente registrado.
        </Text>
      ) : (
        <Stack gap="xs">
          {antecedentes.map((a, i) => (
            <Card key={i} withBorder radius="md" padding="sm">
              <Group justify="space-between" wrap="nowrap" gap="sm">
                <Stack gap={0} style={{ minWidth: 0 }}>
                  <Text size="sm" fw={500}>
                    {TIPO_ANTECEDENTE_OPTIONS.find((o) => o.value === a.tipo)?.label ?? a.tipo}
                    {a.fecha_aproximada && ` — ${a.fecha_aproximada}`}
                  </Text>
                  <Text size="xs" c="dimmed">{a.descripcion}</Text>
                </Stack>

                <ActionIcon
                  color="red"
                  onClick={() => eliminar(i)}
                  aria-label={`Eliminar antecedente ${i + 1}`}
                >
                  <IconTrash size={15} />
                </ActionIcon>
              </Group>
            </Card>
          ))}
        </Stack>
      )}

      {/* Condición especial para urgencias, emergencias y tratamiento médico.
          Se responde SÍ / NO / sin respuesta: en una historia clínica «no
          respondió» no es lo mismo que «respondió que no», y de ahí que el
          valor por defecto sea nulo y no falso. */}
      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed">
          Condición especial para atenciones de urgencia
        </Text>

        <Grid>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <SiNoSinRespuesta
              pregunta="¿Autoriza transfusiones en caso de necesitarlas?"
              valor={fichaData.autoriza_transfusion}
              onChange={(v) => set({ autoriza_transfusion: v })}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, md: 6 }}>
            <SiNoSinRespuesta
              pregunta="¿Está bajo algún tratamiento hormonal?"
              valor={fichaData.tratamiento_hormonal}
              onChange={(v) => set({
                tratamiento_hormonal: v,
                // Si deja de estar en tratamiento, el detalle ya no aplica.
                ...(v ? {} : { tratamiento_hormonal_cual: null }),
              })}
            />
          </Grid.Col>

          {fichaData.tratamiento_hormonal && (
            <Grid.Col span={12}>
              <TextInput
                label="¿Cuál tratamiento hormonal?"
                {...contained}
                value={fichaData.tratamiento_hormonal_cual ?? ''}
                onChange={(e) => set({ tratamiento_hormonal_cual: e.currentTarget.value })}
              />
            </Grid.Col>
          )}
        </Grid>
      </Stack>


      <FemoAntecedentesReproductivosSection
        data={antecedenteReproductivo}
        onChange={onAntecedenteReproductivoChange}
        sexo={sexo}
      />

      <FemoConsumoSustanciasTable
        data={consumoSustancias}
        onChange={onConsumoSustanciasChange}
      />

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed">
          Estilo de vida y condición preexistente
        </Text>

        <Grid>
          <Grid.Col span={{ base: 12, md: 8 }}>
            <TextInput
              label="Actividad física"
              placeholder="Ej: caminata, fútbol"
              {...contained}
              value={fichaData.actividad_fisica_cual ?? ''}
              onChange={(e) => set({ actividad_fisica_cual: e.currentTarget.value })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 4 }}>
            <TextInput
              label="Con qué frecuencia"
              placeholder="Ej: 3 veces por semana"
              {...contained}
              value={fichaData.actividad_fisica_tiempo ?? ''}
              onChange={(e) => set({ actividad_fisica_tiempo: e.currentTarget.value })}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, md: 8 }}>
            <TextInput
              label="Medicación habitual"
              placeholder="Ej: losartán"
              {...contained}
              value={fichaData.medicacion_habitual_cual ?? ''}
              onChange={(e) => set({ medicacion_habitual_cual: e.currentTarget.value })}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 4 }}>
            <TextInput
              label="Dosis y frecuencia"
              placeholder="Ej: 50 mg diarios"
              {...contained}
              value={fichaData.medicacion_habitual_cantidad ?? ''}
              onChange={(e) => set({ medicacion_habitual_cantidad: e.currentTarget.value })}
            />
          </Grid.Col>
        </Grid>
      </Stack>

      <FemoAntecedenteModal
        opened={modalAbierto}
        onClose={modal.close}
        onAgregar={(values) => onAntecedentesChange([...antecedentes, values])}
      />
    </FemoSeccion>
  )
}
