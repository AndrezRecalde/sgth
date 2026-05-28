'use client'

import { useState } from 'react'
import {
  Stack, Group, Text, Badge, Button,
  Skeleton, Grid, Divider, ThemeIcon,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPlus, IconEdit, IconTrash, IconBriefcase,
  IconBuilding, IconId, IconCalendar,
  IconHash, IconShieldCheck,
} from '@tabler/icons-react'
import { DataTable } from 'mantine-datatable'
import { EmptyState } from '@/components/ui/EmptyState'
import { TableActions } from '@/components/ui/TableActions'
import { useContratos } from '../../hooks/useContratos'
import { useContratoMutations } from '../../hooks/useContratoMutations'
import { ContratoModal } from '../ContratoModal'
import type { ContratoConRelaciones, EstadoContrato } from '@/types/api'

const ESTADO_COLORS: Record<EstadoContrato, string> = {
  vigente:   'green',
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

interface DetalleProps {
  contrato: ContratoConRelaciones
}

function ContratoDetalle({ contrato }: DetalleProps) {
  const cargo  = (contrato.puesto as {
    cargo?: {
      nombre?:               string
      denominacion_generica?: string
      clasificacion_personal?: string
    } | null
    es_jefe?:        boolean
    rol_puesto?:     string
    regimen_laboral?: string
  })?.cargo

  const unidad = (contrato.unidad_administrativa as {
    nombre?: string
  })?.nombre

  return (
    <Stack
      gap="sm"
      p="md"
      style={{
        background: 'var(--mantine-color-default-hover)',
        borderTop:  '1px solid var(--mantine-color-default-border)',
      }}
    >
      <Divider
        label="Información del puesto y contrato"
        labelPosition="left"
      />
      <Grid>
        {/* Cargo */}
        <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
          <Group gap="xs" align="flex-start">
            <ThemeIcon size="sm" variant="light" color="emerald" radius="md">
              <IconId size={12} />
            </ThemeIcon>
            <div>
              <Text size="xs" c="dimmed">Cargo</Text>
              <Text size="sm" fw={600}>
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

        {/* Unidad */}
        <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
          <Group gap="xs" align="flex-start">
            <ThemeIcon size="sm" variant="light" color="blue" radius="md">
              <IconBuilding size={12} />
            </ThemeIcon>
            <div>
              <Text size="xs" c="dimmed">Unidad administrativa</Text>
              <Text size="sm" fw={600}>{unidad ?? '—'}</Text>
            </div>
          </Group>
        </Grid.Col>

        {/* Periodo */}
        <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
          <Group gap="xs" align="flex-start">
            <ThemeIcon size="sm" variant="light" color="orange" radius="md">
              <IconCalendar size={12} />
            </ThemeIcon>
            <div>
              <Text size="xs" c="dimmed">Período</Text>
              <Text size="sm" fw={600}>
                {formatFecha(contrato.fecha_inicio)}
                {' → '}
                {contrato.fecha_fin
                  ? formatFecha(contrato.fecha_fin)
                  : 'Indefinido'
                }
              </Text>
            </div>
          </Group>
        </Grid.Col>

        {/* Número contrato */}
        {contrato.numero_contrato && (
          <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
            <Group gap="xs" align="flex-start">
              <ThemeIcon size="sm" variant="light" color="gray" radius="md">
                <IconHash size={12} />
              </ThemeIcon>
              <div>
                <Text size="xs" c="dimmed">Número de contrato</Text>
                <Text size="sm" fw={600} ff="monospace">
                  {contrato.numero_contrato}
                </Text>
              </div>
            </Group>
          </Grid.Col>
        )}

        {/* Resolución */}
        {contrato.resolucion_numero && (
          <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
            <Group gap="xs" align="flex-start">
              <ThemeIcon size="sm" variant="light" color="violet" radius="md">
                <IconShieldCheck size={12} />
              </ThemeIcon>
              <div>
                <Text size="xs" c="dimmed">Número de resolución</Text>
                <Text size="sm" fw={600} ff="monospace">
                  {contrato.resolucion_numero}
                </Text>
              </div>
            </Group>
          </Grid.Col>
        )}

        {/* Código biométrico */}
        {contrato.codigo_marcacion && (
          <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>
            <Group gap="xs" align="flex-start">
              <ThemeIcon size="sm" variant="light" color="grape" radius="md">
                <IconId size={12} />
              </ThemeIcon>
              <div>
                <Text size="xs" c="dimmed">Código biométrico</Text>
                <Text size="sm" fw={600} ff="monospace">
                  {contrato.codigo_marcacion}
                </Text>
              </div>
            </Group>
          </Grid.Col>
        )}
      </Grid>
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

  const lista = contratos as ContratoConRelaciones[]

  if (isLoading) {
    return (
      <Stack gap="xs">
        <Skeleton height={42} radius="md" />
        <Skeleton height={42} radius="md" />
      </Stack>
    )
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

      {lista.length === 0 ? (
        <EmptyState
          icon={IconBriefcase}
          title="Sin contratos registrados"
          description="Registra el primer contrato o nombramiento del servidor."
        />
      ) : (
        <DataTable
          records={lista}
          idAccessor="id"
          columns={[
            {
              accessor: 'tipo_nombramiento',
              title:    'Tipo de nombramiento',
              render: ({ tipo_nombramiento }) => (
                <Text size="sm" fw={500}>
                  {NOMBRAMIENTO_LABELS[tipo_nombramiento ?? '']
                    ?? tipo_nombramiento ?? '—'}
                </Text>
              ),
            },
            {
              accessor: 'cargo',
              title:    'Cargo',
              render: (contrato) => {
                const cargo = (contrato.puesto as {
                  cargo?: { nombre?: string } | null
                })?.cargo
                return (
                  <Text size="sm" c="dimmed">
                    {cargo?.nombre ?? '—'}
                  </Text>
                )
              },
            },
            {
              accessor: 'fecha_inicio',
              title:    'Inicio',
              width:    110,
              render: ({ fecha_inicio }) => (
                <Text size="sm">{formatFecha(fecha_inicio)}</Text>
              ),
            },
            {
              accessor: 'estado',
              title:    'Estado',
              width:    100,
              render: ({ estado }) => (
                <Badge
                  color={ESTADO_COLORS[estado as EstadoContrato] ?? 'gray'}
                  variant="light"
                  size="sm"
                >
                  {ESTADO_LABELS[estado as EstadoContrato] ?? estado ?? '—'}
                </Badge>
              ),
            },
            {
              accessor: 'acciones',
              title:    '',
              width:    50,
              render: (contrato) => (
                <TableActions actions={[
                  {
                    label:   'Editar contrato',
                    icon:    <IconEdit size={14} />,
                    color:   'blue',
                    onClick: () => {
                      setEditContrato(contrato)
                      open()
                    },
                  },
                  {
                    label:   'Eliminar contrato',
                    icon:    <IconTrash size={14} />,
                    color:   'red',
                    onClick: () => {
                      if (confirm(
                        '¿Eliminar este contrato? Esta acción no se puede deshacer.'
                      ))
                        eliminar.mutate(Number(contrato.id))
                    },
                  },
                ]} />
              ),
            },
          ]}
          rowExpansion={{
            content: ({ record }) => (
              <ContratoDetalle contrato={record} />
            ),
          }}
          withTableBorder
          withColumnBorders={false}
          borderRadius="md"
          highlightOnHover
          verticalSpacing="sm"
          minHeight={80}
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
