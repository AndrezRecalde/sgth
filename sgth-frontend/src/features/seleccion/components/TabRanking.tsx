'use client'

import {
  Stack, Text, Group, Badge, Card,
  Button, ThemeIcon, Progress,
  Alert, Skeleton,
} from '@mantine/core'
import {
  IconTrophy, IconMedal, IconMedal2,
  IconInfoCircle, IconCheck,
} from '@tabler/icons-react'
import { useMemo } from 'react'
import { usePostulantes, useDeclararGanador } from '../hooks/useConvocatoria'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  convocatoriaId: number
  estadoConvocatoria: string
}

function PosicionIcon({ pos }: { pos: number }) {
  if (pos === 1) return (
    <ThemeIcon size="md" color="yellow" variant="filled" radius="xl">
      <IconTrophy size={14} />
    </ThemeIcon>
  )
  if (pos === 2) return (
    <ThemeIcon size="md" color="gray" variant="filled" radius="xl">
      <IconMedal size={14} />
    </ThemeIcon>
  )
  if (pos === 3) return (
    <ThemeIcon size="md" color="orange" variant="light" radius="xl">
      <IconMedal2 size={14} />
    </ThemeIcon>
  )
  return (
    <ThemeIcon size="md" color="gray" variant="light" radius="xl">
      <Text size="xs" fw={700}>{pos}</Text>
    </ThemeIcon>
  )
}

const ESTADO_COLORS: Record<string, string> = {
  inscrito:         'gray',
  en_evaluacion:    'blue',
  aprobado:         'emerald',
  reprobado:        'red',
  seleccionado:     'yellow',
  no_seleccionado:  'gray',
  lista_espera:     'orange',
}

const ESTADO_LABELS: Record<string, string> = {
  inscrito:         'Inscrito',
  en_evaluacion:    'En evaluación',
  aprobado:         'Aprobado',
  reprobado:        'Reprobado',
  seleccionado:     '🏆 Seleccionado',
  no_seleccionado:  'No seleccionado',
  lista_espera:     'Lista de espera',
}

export function TabRanking({ convocatoriaId, estadoConvocatoria }: Props) {
  const { data: postulantes = [], isLoading } =
    usePostulantes(convocatoriaId)
  const declarar = useDeclararGanador(convocatoriaId)

  const ranking = useMemo(() => {
    return [...postulantes]
      .filter(p => p.evaluacion)
      .sort((a, b) =>
        (b.evaluacion?.puntaje_total ?? 0) -
        (a.evaluacion?.puntaje_total ?? 0)
      )
  }, [postulantes])

  const sinCalificar = postulantes.filter(p => !p.evaluacion)
  const puedeDeclarar = estadoConvocatoria !== 'finalizada'
  const yaDeclarado   = estadoConvocatoria === 'finalizada'

  const getNombreCompleto = (p: Postulante) =>
    [p.apellidos, p.segundo_apellido, p.nombres, p.segundo_nombre]
      .filter(Boolean).join(' ')

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    )
  }

  return (
    <Stack gap="md" p="md">
      {yaDeclarado && (
        <Alert color="emerald" variant="light"
          icon={<IconTrophy size={16} />}>
          <Text size="xs">
            Esta convocatoria ya fue finalizada.
            El ganador ha sido declarado y se generó
            la solicitud de certificación médica.
          </Text>
        </Alert>
      )}

      {!yaDeclarado && ranking.length === 0 && (
        <Alert color="orange" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Ningún candidato ha sido calificado aún.
            Califique a los candidatos desde el tab
            "Candidatos" para generar el ranking.
          </Text>
        </Alert>
      )}

      {sinCalificar.length > 0 && !yaDeclarado && (
        <Alert color="blue" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            {sinCalificar.length} candidato
            {sinCalificar.length !== 1 ? 's' : ''} aún
            no han sido calificados y no aparecen en
            el ranking.
          </Text>
        </Alert>
      )}

      {ranking.length > 0 && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Ranking de candidatos
            </Text>
            <Badge size="sm" variant="light" color="gray">
              {ranking.length} calificado
              {ranking.length !== 1 ? 's' : ''}
            </Badge>
          </Group>

          {ranking.map((p, i) => {
            const total  = p.evaluacion?.puntaje_total ?? 0
            const aprueba = Number(total) >= 70
            const esGanador = i === 0 && aprueba && puedeDeclarar

            return (
              <Card
                key={p.id}
                withBorder
                radius="md"
                p="sm"
                style={{
                  borderColor: i === 0 && aprueba
                    ? 'var(--mantine-color-yellow-6)'
                    : undefined,
                  borderWidth: i === 0 && aprueba ? 2 : 1,
                }}
              >
                <Stack gap="xs">
                  <Group justify="space-between" wrap="nowrap">
                    <Group gap="sm" wrap="nowrap">
                      <PosicionIcon pos={i + 1} />
                      <Stack gap={0}>
                        <Text size="sm" fw={600}>
                          {getNombreCompleto(p)}
                        </Text>
                        <Text size="xs" c="dimmed">
                          {p.cedula}
                        </Text>
                      </Stack>
                    </Group>
                    <Group gap="xs" wrap="nowrap">
                      <Badge
                        size="sm"
                        variant="light"
                        color={ESTADO_COLORS[p.estado] ?? 'gray'}
                      >
                        {ESTADO_LABELS[p.estado] ?? p.estado}
                      </Badge>
                      <Badge
                        size="lg"
                        variant="light"
                        color={aprueba ? 'emerald' : 'red'}
                      >
                        {Number(total).toFixed(2)} pts
                      </Badge>
                    </Group>
                  </Group>

                  <Group gap="xs">
                    <Text size="xs" c="dimmed">
                      Méritos: {Number(
                        p.evaluacion?.puntaje_meritos ?? 0
                      ).toFixed(2)}
                    </Text>
                    <Text size="xs" c="dimmed">·</Text>
                    <Text size="xs" c="dimmed">
                      Oposición: {Number(
                        p.evaluacion?.puntaje_oposicion ?? 0
                      ).toFixed(2)}
                    </Text>
                  </Group>

                  <Progress
                    value={Number(total)}
                    color={aprueba ? 'emerald' : 'red'}
                    size="xs"
                    radius="xl"
                  />

                  {esGanador && (
                    <Group justify="flex-end">
                      <Button
                        size="xs"
                        color="yellow"
                        leftSection={<IconTrophy size={13} />}
                        loading={declarar.isPending}
                        onClick={() => {
                          if (confirm(
                            `¿Declarar a ${getNombreCompleto(p)} como ganador potencial del concurso?\n\nEsta acción:\n· Cierra la convocatoria\n· Envía solicitud de certificación médica al Dispensario\n\nNota: El expediente del servidor se creará DESPUÉS de que el médico emita el dictamen de aptitud y RRHH confirme la incorporación.`
                          )) {
                            declarar.mutate(p.id)
                          }
                        }}
                      >
                        Declarar ganador
                      </Button>
                    </Group>
                  )}
                </Stack>
              </Card>
            )
          })}
        </Stack>
      )}
    </Stack>
  )
}
