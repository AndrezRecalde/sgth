'use client'

import {
  Stack,
  Textarea,
  Group,
  Text,
  Button,
  Card,
  ActionIcon,
  Badge,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  type FichaBaseForm, type ExamenForm,
  type DiagnosticoFemoForm,
} from '../../schemas/femo.schema'
import { FemoExamenModal } from './FemoExamenModal'
import { FemoDiagnosticosCie10 } from './FemoDiagnosticosCie10'
import { FemoAptitudSelector } from './FemoAptitudSelector'
import { FemoRetiroSection } from './FemoRetiroSection'
import { FemoSeccion } from './FemoSeccion'

interface Props {
  fichaData:        Partial<FichaBaseForm>
  examenes:         ExamenForm[]
  diagnosticos:     DiagnosticoFemoForm[]
  onFichaChange:    (data: Partial<FichaBaseForm>) => void
  onExamenesChange: (data: ExamenForm[]) => void
  onDiagnosticosChange: (data: DiagnosticoFemoForm[]) => void
}

const TIPO_EXAMEN_LABELS: Record<string, string> = {
  laboratorio: 'Laboratorio',
  imagen:      'Imagen',
  otro:        'Otro',
}

export function FemoPaso3({
  fichaData, examenes, diagnosticos,
  onFichaChange, onExamenesChange, onDiagnosticosChange,
}: Props) {
  const contained = useContainedInput()
  const [examenModalOpened,
    { open: abrirExamen, close: cerrarExamen }] = useDisclosure(false)

  const handleEliminarExamen = (idx: number) => {
    onExamenesChange(examenes.filter((_, i) => i !== idx))
  }

  return (
    <Stack gap="xl">
      <FemoSeccion
        letra="J"
        titulo="Exámenes complementarios"
        accion={
          <Button
            size="xs"
            variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={abrirExamen}
          >
            Agregar examen
          </Button>
        }
      >

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
                        {TIPO_EXAMEN_LABELS[ex.tipo]}
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
      </FemoSeccion>

      <FemoDiagnosticosCie10
        diagnosticos={diagnosticos}
        onChange={onDiagnosticosChange}
      />

      <FemoAptitudSelector fichaData={fichaData} onFichaChange={onFichaChange} />

      <FemoSeccion letra="M" titulo="Recomendaciones y tratamiento">
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
      </FemoSeccion>

      {fichaData.tipo_ficha === 'retiro' && (
        <>
          <FemoRetiroSection fichaData={fichaData} onFichaChange={onFichaChange} />
        </>
      )}

      <FemoExamenModal
        opened={examenModalOpened}
        onClose={cerrarExamen}
        onAgregar={(values) => onExamenesChange([...examenes, values])}
      />
    </Stack>
  )
}
