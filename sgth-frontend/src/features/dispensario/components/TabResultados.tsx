'use client'

import { confirmar } from '@/components/ui'
import {
  Stack, Text, Button, Group, Badge,
  Card, ThemeIcon, Skeleton, Anchor,
  ActionIcon, Tooltip,
} from '@mantine/core'
import {
  IconPlus, IconFileText, IconTrash,
  IconExternalLink,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { getIcon } from '@/lib/tablerIcons'
import { SubirResultadoModal } from './SubirResultadoModal'
import { EmptyState } from '@/components/ui/EmptyState'
import {
  useResultadosPorConsulta,
  useEliminarResultado,
} from '../hooks/useResultadoMedico'
import {
  TIPO_RESULTADO_OPTIONS,
  TIPO_RESULTADO_ICONS,
} from '../services/resultadoMedicoService'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  turno:             AgendaMedica
  consulta:          ConsultaMedica
  historiaClinicaId: number
}

function getLabelTipo(tipo: string): string {
  return TIPO_RESULTADO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo
}

function getArchivoUrl(ruta: string): string {
  const base = (process.env.NEXT_PUBLIC_API_URL ?? 'http://sgth.test/api/v1')
    .replace('/api/v1', '')
  return `${base}/storage/${ruta}`
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function TabResultados({
  turno, consulta, historiaClinicaId,
}: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  const { data: resultados = [], isLoading } =
    useResultadosPorConsulta(historiaClinicaId, consulta.id)

  const eliminar = useEliminarResultado(consulta.id)

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Resultados de esta consulta
          {resultados.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({resultados.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          color="emerald"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
        >
          Subir resultado
        </Button>
      </Group>

      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={70} radius="md" />
          <Skeleton height={70} radius="md" />
        </Stack>
      ) : resultados.length === 0 ? (
        <EmptyState
          icon={IconFileText}
          title="Sin resultados"
          description="No se han subido resultados médicos
            para esta consulta."
        />
      ) : (
        <Stack gap="sm">
          {resultados.map((r) => (
            <Card key={r.id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="sm" wrap="nowrap">
                  <ThemeIcon
                    size="md" variant="light" color="blue" radius="xl"
                  >
                    {getIcon(TIPO_RESULTADO_ICONS[r.tipo] ?? 'IconFileText', 16)}
                  </ThemeIcon>
                  <Stack gap={0}>
                    <Group gap="xs">
                      <Badge
                        size="xs"
                        variant="light"
                        color="blue"
                      >
                        {getLabelTipo(r.tipo)}
                      </Badge>
                      <Text size="xs" c="dimmed">
                        {formatFecha(r.fecha_resultado)}
                      </Text>
                    </Group>
                    <Text size="sm">{r.descripcion}</Text>
                    {r.subido_por?.servidor && (
                      <Text size="xs" c="dimmed">
                        Dr. {r.subido_por.servidor.nombre}{' '}
                        {r.subido_por.servidor.apellido}
                      </Text>
                    )}
                  </Stack>
                </Group>

                <Group gap="xs" wrap="nowrap">
                  <Tooltip label="Ver archivo" withArrow>
                    <Anchor
                      href={getArchivoUrl(r.archivo)}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <ActionIcon
                        size="sm"
                        variant="light"
                        color="blue"
                      >
                        <IconExternalLink size={13} />
                      </ActionIcon>
                    </Anchor>
                  </Tooltip>
                  <Tooltip label="Eliminar" withArrow>
                    <ActionIcon
                      size="sm"
                      variant="light"
                      color="red"
                      onClick={() => confirmar({
                        title:   'Eliminar resultado',
                        message: 'Se eliminará este resultado de laboratorio. No se puede deshacer.',
                        destructiva: true,
                        onConfirm: () => eliminar.mutate(r.id),
                      })}
                    >
                      <IconTrash size={13} />
                    </ActionIcon>
                  </Tooltip>
                </Group>
              </Group>
            </Card>
          ))}
        </Stack>
      )}

      <SubirResultadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        historiaClinicaId={historiaClinicaId}
      />
    </Stack>
  )
}
