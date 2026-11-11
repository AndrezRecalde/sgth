import { Alert, List, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { DetailList, SectionHeading, StatusBadge } from '@/components/ui'
import { NIVELES_COMPETENCIA, NIVELES_DEL_PUESTO, NIVELES_INSTRUCCION, TIPOS_COMPETENCIA, textoExperiencia } from '../constants/perfilPuesto'
import type { FichaManualDetalle } from '../services/manualPuestosService'

const etiqueta = (lista: readonly { value: string; label: string }[], valor: string | null) =>
  lista.find((x) => x.value === valor)?.label ?? valor

/**
 * Lo que la ficha del Manual de Puestos carga en el puesto (P4, PR 12), ya en
 * los términos del perfil, y los avisos de la importación: lo que TH mira
 * antes de confirmar.
 */
export function FichaManualPropuesta({ ficha }: { ficha: FichaManualDetalle }) {
  const { propuesta } = ficha

  return (
    <Stack gap="lg">
      {ficha.avisos.length > 0 && (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />} title="Lo que la importación interpretó">
          <List size="sm" spacing={4}>
            {ficha.avisos.map((aviso) => <List.Item key={aviso}>{aviso}</List.Item>)}
          </List>
        </Alert>
      )}

      <div>
        <SectionHeading title="Identificación" />
        <DetailList items={[
          { label: 'Unidad en el manual', value: ficha.unidad, ancho: true },
          { label: 'Subproceso', value: propuesta.subproceso },
          { label: 'Grupo ocupacional', value: [ficha.grupo_ocupacional, ficha.grado ? `grado ${ficha.grado}` : null].filter(Boolean).join(', ') },
          { label: 'Nivel', value: etiqueta(NIVELES_DEL_PUESTO, propuesta.nivel) },
          { label: 'Rol', value: ficha.ficha.rol },
          { label: 'Plazas en el manual', value: ficha.plazas },
          { label: 'Ámbito', value: ficha.ficha.ambito },
        ]} />
      </div>

      <div>
        <SectionHeading title="Misión" />
        <Text size="sm">{propuesta.mision ?? 'La ficha no trae misión.'}</Text>
      </div>

      <div>
        <SectionHeading title="Instrucción y experiencia" />
        <Stack gap={6}>
          {propuesta.requisitos.length === 0 && <Text size="sm" c="dimmed">La ficha no trae instrucción formal.</Text>}
          {propuesta.requisitos.map((r, i) => (
            <div key={i}>
              {r.nivel_instruccion
                ? <Text size="sm">{etiqueta(NIVELES_INSTRUCCION, r.nivel_instruccion)}{r.detalle ? ` (${r.detalle})` : ''}, {textoExperiencia(r.meses_experiencia ?? 0)}</Text>
                : <Text size="sm" c="red">No se reconoció el nivel: no se carga.</Text>}
              <Text size="xs" c="dimmed">En la ficha: «{r.texto}»{r.experiencia ? `, «${r.experiencia}»` : ''}</Text>
            </div>
          ))}
        </Stack>
      </div>

      <div>
        <SectionHeading title="Conocimientos, experiencia y capacitación" />
        <DetailList columnas={1} items={[
          { label: 'Área de conocimiento', value: propuesta.area_conocimiento },
          { label: 'Especificidad de la experiencia', value: propuesta.especificidad_experiencia },
          { label: 'Capacitación', value: propuesta.capacitacion },
          { label: 'Relaciones internas y externas', value: propuesta.relaciones },
        ]} />
      </div>

      <div>
        <SectionHeading title={`Actividades esenciales (${propuesta.actividades.length})`} />
        <List type="ordered" size="sm" spacing={8}>
          {propuesta.actividades.map((a, i) => (
            <List.Item key={i}>
              <Text size="sm">{a.descripcion}</Text>
              {a.conocimientos && <Text size="xs" c="dimmed">Conocimientos: {a.conocimientos}</Text>}
            </List.Item>
          ))}
        </List>
      </div>

      {TIPOS_COMPETENCIA.map((tipo) => {
        const deEsteTipo = propuesta.competencias.filter((c) => c.tipo === tipo.value)
        return (
          <div key={tipo.value}>
            <SectionHeading title={`${tipo.plural} (${deEsteTipo.length})`} />
            {deEsteTipo.length === 0 && <Text size="sm" c="dimmed">Ninguna.</Text>}
            <Stack gap={4}>
              {deEsteTipo.map((c) => (
                <Text key={c.competencia_id} size="sm">
                  {c.nombre} <StatusBadge size="xs">{etiqueta(NIVELES_COMPETENCIA, c.nivel)}</StatusBadge>{' '}
                  <Text span size="xs" c="dimmed">comportamiento {c.numero}</Text>
                </Text>
              ))}
            </Stack>
          </div>
        )
      })}
    </Stack>
  )
}
