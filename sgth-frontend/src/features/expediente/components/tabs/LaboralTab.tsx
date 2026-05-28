'use client'

import { useState } from 'react'
import {
  Stack, Group, Text, Badge, Button,
  Skeleton, Collapse, Grid, Divider,
  ThemeIcon, ActionIcon, Tooltip,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPlus, IconEdit, IconTrash, IconBriefcase,
  IconChevronDown, IconChevronRight,
  IconBuilding, IconId, IconCalendar,
  IconHash, IconShieldCheck,
} from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { TableActions } from '@/components/ui/TableActions'
import { useContratos } from '../../hooks/useContratos'
import { useContratoMutations } from '../../hooks/useContratoMutations'
import { ContratoModal } from '../ContratoModal'
import type { ContratoConRelaciones, EstadoContrato } from '@/types/api'

const ESTADO_COLORS: Record<EstadoContrato, string> = {
  vigente:   'emerald',
  terminado: 'gray',
  cancelado: 'red',
}

const ESTADO_LABELS: Record<EstadoContrato, string> = {
  vigente:   'Vigente',
  terminado: 'Terminado',
  cancelado: 'Cancelado',
}

const NOMBRAMIENTO_LABELS: Record<string, string> = {
  nombramiento_permanente:     'Nombramiento Permanente',
  nombramiento_provisional:    'Nombramiento Provisional',
  servicios_ocasionales:       'Servicios Ocasionales',
  libre_nombramiento_remocion: 'Libre Nombramiento y Remoción',
  codigo_trabajo:              'Código del Trabajo',
  servicios_profesionales:     'Servicios Profesionales',
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    timeZone: 'UTC',
  })
}

interface ContratoRowProps {
  contrato:  ContratoConRelaciones
  onEdit:    (c: ContratoConRelaciones) => void
  onDelete:  (id: number) => void
}

function ContratoRow({ contrato, onEdit, onDelete }: ContratoRowProps) {
  const [expanded, setExpanded] = useState(false)

  const estadoColor = ESTADO_COLORS[contrato.estado as EstadoContrato] ?? 'gray'
  const estadoLabel = ESTADO_LABELS[contrato.estado as EstadoContrato] ?? contrato.estado

  const cargo = (contrato.puesto as {
    cargo?: { nombre?: string; denominacion_generica?: string; clasificacion_personal?: string }
    es_jefe?: boolean
    rol_puesto?: string
    regimen_laboral?: string
  })?.cargo

  const unidad = (contrato.unidad_administrativa as { nombre?: string })?.nombre

  return (
    <Stack gap={0}>
      {/* Fila principal */}
      <Group
        justify="space-between"
        p="sm"
        style={{
          borderRadius: expanded ? '8px 8px 0 0' : 8,
          border: '1px solid var(--mantine-color-default-border)',
          borderBottom: expanded
            ? '1px solid var(--mantine-color-emerald-3)'
            : undefined,
          background: expanded
            ? 'var(--mantine-color-emerald-light)'
            : contrato.estado === 'vigente'
              ? 'var(--mantine-color-emerald-light-hover)'
              : undefined,
          cursor: 'pointer',
        }}
        onClick={() => setExpanded(e => !e)}
      >
        <Group gap="sm">
          {expanded
            ? <IconChevronDown size={14} />
            : <IconChevronRight size={14} />
          }
          <div>
            <Group gap="xs" mb={2}>
              <Text size="sm" fw={600}>
                {NOMBRAMIENTO_LABELS[contrato.tipo_nombramiento ?? '']
                  ?? contrato.tipo_nombramiento ?? '-'}
              </Text>
              <Badge
                color={estadoColor}
                variant="light"
                size="xs"
              >
                {estadoLabel}
              </Badge>
            </Group>
            <Text size="xs" c="dimmed">
              {cargo?.nombre ?? 'Sin cargo asignado'}
              {unidad ? ` · ${unidad}` : ''}
            </Text>
          </div>
        </Group>

        <Group gap="xs" onClick={e => e.stopPropagation()}>
          <Text size="xs" c="dimmed">
            {formatFecha(contrato.fecha_inicio)}
            {contrato.fecha_fin
              ? ` → ${formatFecha(contrato.fecha_fin)}`
              : ' → Indefinido'}
          </Text>
          <TableActions actions={[
            {
              label: 'Editar contrato',
              icon:  <IconEdit size={14} />,
              color: 'blue',
              onClick: () => onEdit(contrato),
            },
            {
              label: 'Eliminar contrato',
              icon:  <IconTrash size={14} />,
              color: 'red',
              onClick: () => {
                if (confirm('¿Eliminar este contrato? Esta acción no se puede deshacer.'))
                  onDelete(Number(contrato.id))
              },
            },
          ]} />
        </Group>
      </Group>

      {/* Panel expandido con detalle del puesto */}
      <Collapse expanded={expanded}>
        <Stack
          gap="sm"
          p="md"
          style={{
            border: '1px solid var(--mantine-color-default-border)',
            borderTop: 'none',
            borderRadius: '0 0 8px 8px',
            background: 'var(--mantine-color-body)',
          }}
        >
          <Divider label="Información del puesto" labelPosition="left" />

          <Grid>
            {/* Cargo */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Group gap="xs" align="flex-start">
                <ThemeIcon
                  size="sm" variant="light" color="emerald" radius="md"
                >
                  <IconId size={12} />
                </ThemeIcon>
                <div>
                  <Text size="xs" c="dimmed">Cargo</Text>
                  <Text size="sm" fw={500}>
                    {cargo?.nombre ?? '—'}
                  </Text>
                  {cargo?.denominacion_generica && (
                    <Text size="xs" c="dimmed">
                      {cargo.denominacion_generica}
                    </Text>
                  )}
                </div>
              </Group>
            </Grid.Col>

            {/* Unidad administrativa */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Group gap="xs" align="flex-start">
                <ThemeIcon
                  size="sm" variant="light" color="blue" radius="md"
                >
                  <IconBuilding size={12} />
                </ThemeIcon>
                <div>
                  <Text size="xs" c="dimmed">Unidad administrativa</Text>
                  <Text size="sm" fw={500}>
                    {unidad ?? '—'}
                  </Text>
                </div>
              </Group>
            </Grid.Col>

            {/* Número de contrato */}
            {contrato.numero_contrato && (
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Group gap="xs" align="flex-start">
                  <ThemeIcon
                    size="sm" variant="light" color="gray" radius="md"
                  >
                    <IconHash size={12} />
                  </ThemeIcon>
                  <div>
                    <Text size="xs" c="dimmed">Número de contrato</Text>
                    <Text size="sm" fw={500} ff="monospace">
                      {contrato.numero_contrato}
                    </Text>
                  </div>
                </Group>
              </Grid.Col>
            )}

            {/* Resolución */}
            {contrato.resolucion_numero && (
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Group gap="xs" align="flex-start">
                  <ThemeIcon
                    size="sm" variant="light" color="gray" radius="md"
                  >
                    <IconShieldCheck size={12} />
                  </ThemeIcon>
                  <div>
                    <Text size="xs" c="dimmed">Número de resolución</Text>
                    <Text size="sm" fw={500} ff="monospace">
                      {contrato.resolucion_numero}
                    </Text>
                  </div>
                </Group>
              </Grid.Col>
            )}

            {/* Fechas */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Group gap="xs" align="flex-start">
                <ThemeIcon
                  size="sm" variant="light" color="orange" radius="md"
                >
                  <IconCalendar size={12} />
                </ThemeIcon>
                <div>
                  <Text size="xs" c="dimmed">Periodo</Text>
                  <Text size="sm" fw={500}>
                    {formatFecha(contrato.fecha_inicio)}
                    {' → '}
                    {contrato.fecha_fin
                      ? formatFecha(contrato.fecha_fin)
                      : <Text span size="sm" c="dimmed">Indefinido</Text>
                    }
                  </Text>
                </div>
              </Group>
            </Grid.Col>

            {/* Código de marcación */}
            {contrato.codigo_marcacion && (
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Group gap="xs" align="flex-start">
                  <ThemeIcon
                    size="sm" variant="light" color="violet" radius="md"
                  >
                    <IconId size={12} />
                  </ThemeIcon>
                  <div>
                    <Text size="xs" c="dimmed">Código de marcación biométrica</Text>
                    <Text size="sm" fw={500} ff="monospace">
                      {contrato.codigo_marcacion}
                    </Text>
                  </div>
                </Group>
              </Grid.Col>
            )}
          </Grid>
        </Stack>
      </Collapse>
    </Stack>
  )
}

interface Props { servidorId: number }

export function LaboralTab({ servidorId }: Props) {
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [editContrato, setEditContrato] =
    useState<ContratoConRelaciones | null>(null)

  const { data: contratos = [], isLoading } = useContratos(servidorId)
  const { eliminar } = useContratoMutations(servidorId)

  const handleClose = () => {
    setEditContrato(null)
    close()
  }

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button
          size="xs"
          color="emerald"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={() => { setEditContrato(null); open() }}
        >
          Nuevo contrato
        </Button>
      </Group>

      {isLoading ? (
        <Stack gap="xs">
          <Skeleton height={64} radius="md" />
          <Skeleton height={64} radius="md" />
        </Stack>
      ) : Array.isArray(contratos) && contratos.length > 0 ? (
        <Stack gap="xs">
          {(contratos as ContratoConRelaciones[]).map(c => (
            <ContratoRow
              key={c.id}
              contrato={c}
              onEdit={(item) => { setEditContrato(item); open() }}
              onDelete={(id) => eliminar.mutate(id)}
            />
          ))}
        </Stack>
      ) : (
        <EmptyState
          icon={IconBriefcase}
          title="Sin contratos registrados"
          description="Registra el primer contrato o nombramiento del servidor."
        />
      )}

      <ContratoModal
        opened={modalOpened}
        onClose={handleClose}
        servidorId={servidorId}
        contrato={editContrato}
      />
    </Stack>
  )
}
