'use client'

import { Alert, Card, Group, List, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { SectionHeading, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { NIVELES_COMPETENCIA, TIPOS_COMPETENCIA, textoExperiencia } from '@/features/estructura/constants/perfilPuesto'
import { faltaDelPerfil } from '../constants/perfilConvocatoria'
import type { PerfilDeLaConvocatoria } from '../services/convocatoriaService'

function Dato({ titulo, valor }: { titulo: string; valor?: string | null }) {
  return (
    <div>
      <Text size="xs" c="dimmed">{titulo}</Text>
      <Text size="sm" c={valor ? undefined : 'dimmed'} style={{ whiteSpace: 'pre-line' }}>
        {valor || 'Sin registrar'}
      </Text>
    </div>
  )
}

interface Props {
  perfil:   PerfilDeLaConvocatoria | null | undefined
  borrador: boolean
}

/**
 * El perfil del puesto que anuncia la convocatoria (docs/diseno-manual-de-
 * puestos.md, P7, PR 16): misión, requisitos, capacitación, competencias y
 * actividades salen del perfil, no de un texto escrito a mano. En borrador es
 * el vigente; al publicar, la convocatoria lo guarda.
 */
export function ConvocatoriaPerfil({ perfil, borrador }: Props) {
  if (!perfil) {
    return <Text size="sm" c="dimmed">La convocatoria no tiene un puesto del que tomar el perfil.</Text>
  }

  return (
    <Stack gap="lg">
      <Origen perfil={perfil} borrador={borrador} />

      <Stack gap="xs">
        <SectionHeading title="Misión" />
        <Text size="sm" c={perfil.mision ? undefined : 'dimmed'} style={{ whiteSpace: 'pre-line' }}>
          {perfil.mision || 'Sin registrar'}
        </Text>
      </Stack>

      <Stack gap="xs">
        <SectionHeading title="Requisitos" />
        {perfil.requisitos.length === 0 ? (
          <Text size="sm" c="dimmed">Sin alternativas de instrucción registradas.</Text>
        ) : (
          <Stack gap={4}>
            <Text size="xs" c="dimmed">Instrucción formal y experiencia, una de estas alternativas:</Text>
            {perfil.requisitos.map((r, i) => (
              <Card key={i} withBorder radius="md" p="xs">
                <Text size="sm" fw={500}>{r.instruccion}{r.detalle ? ` (${r.detalle})` : ''}</Text>
                <Text size="xs" c="dimmed">Experiencia: {textoExperiencia(r.meses_experiencia)}</Text>
              </Card>
            ))}
          </Stack>
        )}
        <Dato titulo="Área de conocimiento" valor={perfil.area_conocimiento} />
        <Dato titulo="Especificidad de la experiencia" valor={perfil.especificidad_experiencia} />
        <Dato titulo="Capacitación" valor={perfil.capacitacion} />
      </Stack>

      <Stack gap="xs">
        <SectionHeading title="Competencias" />
        {perfil.competencias.length === 0 ? (
          <Text size="sm" c="dimmed">Sin competencias registradas.</Text>
        ) : TIPOS_COMPETENCIA.map(({ value, plural }) => {
          const deEsteTipo = perfil.competencias.filter((c) => c.tipo === value)
          if (deEsteTipo.length === 0) return null
          return (
            <Stack key={value} gap={4}>
              <Text size="xs" c="dimmed">{plural}</Text>
              {deEsteTipo.map((c) => (
                <Card key={c.nombre} withBorder radius="md" p="xs">
                  <Group justify="space-between" wrap="nowrap" gap="xs">
                    <Text size="sm" fw={500}>{c.nombre}</Text>
                    <StatusBadge>{NIVELES_COMPETENCIA.find((n) => n.value === c.nivel)?.label ?? c.nivel}</StatusBadge>
                  </Group>
                  {c.comportamiento && <Text size="xs" c="dimmed" mt={4}>{c.comportamiento}</Text>}
                </Card>
              ))}
            </Stack>
          )
        })}
      </Stack>

      <Stack gap="xs">
        <SectionHeading title="Actividades esenciales" />
        {perfil.actividades.length === 0 ? (
          <Text size="sm" c="dimmed">Sin actividades registradas.</Text>
        ) : (
          <List type="ordered" size="sm" spacing={4}>
            {perfil.actividades.map((a, i) => <List.Item key={i}>{a}</List.Item>)}
          </List>
        )}
      </Stack>
    </Stack>
  )
}

/** De dónde sale el perfil y qué pasa al publicar. */
function Origen({ perfil, borrador }: Props & { perfil: PerfilDeLaConvocatoria }) {
  if (perfil.fuente === 'convocatoria') {
    return (
      <Text size="xs" c="dimmed">
        Guardado al publicar la convocatoria{perfil.tomado_en ? `, el ${formatFecha(perfil.tomado_en)}` : ''}. Lo que
        cambie después en el puesto no la afecta.
      </Text>
    )
  }

  if (!borrador) {
    return (
      <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
        La convocatoria se publicó antes de que el sistema guardara el perfil: este es el perfil actual del puesto,
        que pudo cambiar desde entonces.
      </Alert>
    )
  }

  const falta = faltaDelPerfil(perfil)
  if (falta) {
    return (
      <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
        El perfil del puesto no tiene {falta}. Complételo en Puestos y
        cargos antes de publicar: al publicar, la convocatoria guarda el perfil tal como esté.
      </Alert>
    )
  }

  return (
    <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
      Es el perfil vigente del puesto. Al publicar, la convocatoria lo guarda tal como esté, y lo que cambie después
      en el puesto ya no la afecta.
    </Alert>
  )
}
