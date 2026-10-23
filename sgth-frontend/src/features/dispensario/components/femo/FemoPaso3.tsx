'use client'

import {
  Stack,
  Textarea,
  Group,
  Text,
  Button,
  Card,
  ActionIcon,
  Tooltip,
  } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFileImport, IconPaperclip, IconPlus, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { type FichaBaseForm } from '../../schemas/femo.schema'
import { type ExamenForm, type DiagnosticoFemoForm } from '../../schemas/femoEvaluacion.schema'
import { FemoExamenModal } from './FemoExamenModal'
import { ResultadosParaFemoModal } from './ResultadosParaFemoModal'
import { FemoDiagnosticosCie10 } from './FemoDiagnosticosCie10'
import { FemoAptitudSelector } from './FemoAptitudSelector'
import { FemoRetiroSection } from './FemoRetiroSection'
import { FemoSeccion } from './FemoSeccion'
import { confirmar, StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  /** El paciente, para traer sus resultados del Dispensario a la sección J. */
  cedula?:          string
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
  cedula, fichaData, examenes, diagnosticos,
  onFichaChange, onExamenesChange, onDiagnosticosChange,
}: Props) {
  const contained = useContainedInput()
  const [examenModalOpened,
    { open: abrirExamen, close: cerrarExamen }] = useDisclosure(false)
  const [resultadosOpened,
    { open: abrirResultados, close: cerrarResultados }] = useDisclosure(false)

  const handleEliminarExamen = (idx: number) => {
    confirmar({
      title: 'Quitar examen',
      message: <>Se quitará <b>{examenes[idx].nombre_examen}</b> de la sección J.</>,
      confirmLabel: 'Quitar',
      destructiva: true,
      onConfirm: () => onExamenesChange(examenes.filter((_, i) => i !== idx)),
    })
  }

  return (
    <Stack gap="xl">
      <FemoSeccion
        letra="J"
        titulo="Exámenes complementarios"
        accion={
          <Group gap="xs">
            {cedula && (
              <Button size="xs" variant="light" leftSection={<IconFileImport size={14} />} onClick={abrirResultados}>
                Traer de resultados
              </Button>
            )}
            <Button
              size="xs"
              variant="light"
              leftSection={<IconPlus size={14} />}
              onClick={abrirExamen}
            >
              Agregar examen
            </Button>
          </Group>
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
                      <StatusBadge size="xs">
                        {TIPO_EXAMEN_LABELS[ex.tipo]}
                      </StatusBadge>
                      <Text size="sm" fw={500}>
                        {ex.nombre_examen}
                      </Text>
                      {ex.resultado_medico_id && (
                        <Tooltip label="Copiado de un resultado del Dispensario" withArrow>
                          <Text span c="dimmed" lh={0}><IconPaperclip size={13} /></Text>
                        </Tooltip>
                      )}
                    </Group>
                    {ex.resultado && (
                      <Text size="xs" c="dimmed">{ex.resultado}</Text>
                    )}
                    {ex.fecha_examen && (
                      <Text size="xs" c="dimmed">
                        {formatFechaMes(ex.fecha_examen)}
                      </Text>
                    )}
                  </Stack>
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    aria-label={`Quitar el examen ${ex.nombre_examen}`}
                    onClick={() => handleEliminarExamen(i)}
                  >
                    <IconTrash size={13} />
                  </ActionIcon>
                </Group>
              </Card>
            ))}
          </Stack>
        )}

        <Textarea
          label="Observaciones"
          description="Observaciones de la sección J (resultados de exámenes)"
          autosize
          minRows={2}
          {...contained}
          value={fichaData.observacion_examenes ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, observacion_examenes: e.currentTarget.value,
          })}
        />
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
      </FemoSeccion>

      {fichaData.tipo_ficha === 'retiro' && (
        <>
          <FemoRetiroSection fichaData={fichaData} onFichaChange={onFichaChange} />
        </>
      )}

      <ResultadosParaFemoModal
        opened={resultadosOpened}
        onClose={cerrarResultados}
        cedula={cedula}
        hasta={fichaData.fecha_evaluacion}
        yaCopiados={examenes.map((e) => e.resultado_medico_id).filter((id): id is number => !!id)}
        onAgregar={(filas) => onExamenesChange([...examenes, ...filas])}
      />

      <FemoExamenModal
        opened={examenModalOpened}
        onClose={cerrarExamen}
        onAgregar={(values) => onExamenesChange([...examenes, values])}
      />
    </Stack>
  )
}
