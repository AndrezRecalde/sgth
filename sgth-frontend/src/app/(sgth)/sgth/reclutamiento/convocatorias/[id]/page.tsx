'use client'

import { useState } from 'react'
import { use } from 'react'
import {
  Stack, Group, Badge, Text, Button,
  Card, Grid, Tabs, Divider,
  ThemeIcon, Skeleton,
} from '@mantine/core'
import {
  IconSpeakerphone, IconArrowLeft,
  IconUsers, IconWorldUpload,
  IconCalendar,
  IconPlus,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { SgthTable } from '@/components/ui/SgthTable'
import { TabRanking } from '@/features/seleccion/components/TabRanking'
import { IconChartBar } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import {
  useConvocatoriaDetalle,
  usePostulantes,
  usePublicarConvocatoria,
  useConfirmarGanador,
} from '@/features/seleccion/hooks/useConvocatoria'
import { IconCircleCheck } from '@tabler/icons-react'
import {
  ESTADO_CONVOCATORIA_COLORS,
  ESTADO_CONVOCATORIA_OPTIONS,
  TIPO_CONVOCATORIA_OPTIONS,
  ESTADO_POSTULANTE_OPTIONS,
} from '@/features/seleccion/services/convocatoriaService'
import type { Postulante } from
  '@/features/seleccion/services/convocatoriaService'
import type { DataTableColumn } from 'mantine-datatable'
import { InscribirPostulanteModal } from
  '@/features/seleccion/components/InscribirPostulanteModal'
import { useDisclosure } from '@mantine/hooks'
import { CalificarPostulanteModal } from
  '@/features/seleccion/components/CalificarPostulanteModal'
import { IconStar, IconClipboardList, IconEdit } from '@tabler/icons-react'
import { TabCriterios } from
  '@/features/seleccion/components/TabCriterios'

interface Props {
  params: Promise<{ id: string }>
}

export default function DetalleConvocatoriaPage({ params }: Props) {
  const { id } = use(params)
  const convocatoriaId = Number(id)
  const router   = useRouter()
  const publicar = usePublicarConvocatoria()
  const confirmarGanador = useConfirmarGanador(convocatoriaId)
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const [postulanteSel, setPostulanteSel] =
    useState<Postulante | null>(null)
  const [calModalOpened,
    { open: abrirCalModal, close: cerrarCalModal }] =
    useDisclosure(false)

  const { data: convocatoria, isLoading } =
    useConvocatoriaDetalle(convocatoriaId)
  const { data: postulantes = [], isLoading: cargandoPostulantes } =
    usePostulantes(convocatoriaId)

  const getLabelEstado = (v: string) =>
    ESTADO_CONVOCATORIA_OPTIONS.find(o => o.value === v)?.label ?? v

  const getLabelTipo = (v: string) =>
    TIPO_CONVOCATORIA_OPTIONS.find(o => o.value === v)?.label ?? v

  const getLabelEstadoPostulante = (v: string) =>
    ESTADO_POSTULANTE_OPTIONS.find(o => o.value === v)?.label ?? v

  const columnsPostulantes: DataTableColumn<Postulante>[] = [
    {
      accessor: 'cedula',
      title:    'Cédula',
      width:    120,
      render: (p) => (
        <Text size="sm" ff="monospace">{p.cedula}</Text>
      ),
    },
    {
      accessor: 'nombres',
      title:    'Candidato',
      render: (p) => {
        const nombreCompleto = [
          p.apellidos,
          p.segundo_apellido,
          p.nombres,
          p.segundo_nombre,
        ].filter(Boolean).join(' ')
        return (
          <Stack gap={0}>
            <Text size="sm" fw={500}>{nombreCompleto}</Text>
            <Text size="xs" c="dimmed">{p.correo}</Text>
          </Stack>
        )
      },
    },
    {
      accessor: 'telefono',
      title:    'Teléfono',
      width:    120,
      render: (p) => (
        <Text size="sm">{p.telefono ?? '—'}</Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    140,
      render: (p) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelEstadoPostulante(p.estado)}
        </Badge>
      ),
    },
    {
      accessor: 'evaluacion',
      title:    'Puntaje',
      width:    90,
      render: (p) => (
        <Text size="sm" ta="center" fw={500}>
          {p.evaluacion
            ? `${p.evaluacion.puntaje_total}/100`
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (p) => (
        <TableActions actions={[
          {
            label:   p.evaluacion ? 'Editar calificación' : 'Calificar',
            icon:    p.evaluacion
              ? <IconEdit size={14} />
              : <IconStar size={14} />,
            color:   p.evaluacion ? 'blue' : 'orange',
            onClick: () => {
              setPostulanteSel(p)
              abrirCalModal()
            },
          },
          {
            label:   'Ver perfil',
            icon:    <IconUsers size={14} />,
            color:   'blue',
            onClick: () => router.push(
              `/sgth/reclutamiento/convocatorias/${convocatoriaId}/postulantes/${p.id}`
            ),
          },
        ]} />
      ),
    },
  ]

  if (isLoading) {
    return (
      <Stack gap="md">
        <Skeleton height={80} radius="lg" />
        <Skeleton height={200} radius="lg" />
      </Stack>
    )
  }

  if (!convocatoria) return null

  return (
    <Stack gap="md">
      <PageHeader
        title={convocatoria.titulo}
        subtitle={convocatoria.codigo}
        icon={<IconSpeakerphone size={24} />}
        actions={
          <Group gap="xs">
            <Button
              variant="default"
              leftSection={<IconArrowLeft size={14} />}
              onClick={() =>
                router.push('/sgth/reclutamiento/convocatorias')
              }
            >
              Volver
            </Button>
            {convocatoria.estado === 'borrador' && (
              <Button
                color="blue"
                leftSection={<IconWorldUpload size={14} />}
                loading={publicar.isPending}
                onClick={() => {
                  if (confirm('¿Publicar esta convocatoria?')) {
                    publicar.mutate(convocatoriaId)
                  }
                }}
              >
                Publicar convocatoria
              </Button>
            )}
            {convocatoria.estado === 'en_evaluacion_medica' && (
              <Button
                color="emerald"
                leftSection={<IconCircleCheck size={14} />}
                loading={confirmarGanador.isPending}
                onClick={() => {
                  if (confirm(
                    '¿Declarar ganador oficial al candidato aprobado por el Dispensario?\n\n' +
                    'Esta acción finaliza la convocatoria definitivamente.'
                  )) {
                    confirmarGanador.mutate()
                  }
                }}
              >
                Declarar ganador oficial
              </Button>
            )}
          </Group>
        }
      />

      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Card withBorder radius="lg" p="lg">
            <Stack gap="sm">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Información general
              </Text>
              <Text size="sm">{convocatoria.descripcion}</Text>
              <Divider />
              <Grid>
                <Grid.Col span={6}>
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">Puesto</Text>
                    <Text size="sm" fw={500}>
                      {convocatoria.puesto?.cargo?.nombre ?? '—'}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {convocatoria.puesto?.unidad_administrativa
                        ?.nombre ?? ''}
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={6}>
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">Modalidad</Text>
                    <Badge
                      size="sm"
                      variant="light"
                      color="blue"
                    >
                      {getLabelTipo(convocatoria.tipo)}
                    </Badge>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={6}>
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">Período</Text>
                    <Group gap="xs">
                      <ThemeIcon
                        size="xs"
                        color="gray"
                        variant="subtle"
                      >
                        <IconCalendar size={12} />
                      </ThemeIcon>
                      <Text size="sm">
                        {new Date(convocatoria.fecha_inicio)
                          .toLocaleDateString('es-EC', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                          })}
                        {' — '}
                        {new Date(convocatoria.fecha_fin)
                          .toLocaleDateString('es-EC', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                          })}
                      </Text>
                    </Group>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={6}>
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">Vacantes</Text>
                    <Text size="sm" fw={500}>
                      {convocatoria.vacantes} vacante
                      {convocatoria.vacantes !== 1 ? 's' : ''}
                    </Text>
                  </Stack>
                </Grid.Col>
              </Grid>
            </Stack>
          </Card>
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 4 }}>
          <Card withBorder radius="lg" p="lg">
            <Stack gap="sm">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Estado del proceso
              </Text>
              <Badge
                size="lg"
                variant="light"
                color={ESTADO_CONVOCATORIA_COLORS[
                  convocatoria.estado
                ] ?? 'gray'}
              >
                {getLabelEstado(convocatoria.estado)}
              </Badge>
              <Divider />
              <Stack gap={4}>
                <Text size="xs" c="dimmed">Candidatos inscritos</Text>
                <Text size="xl" fw={700}>
                  {postulantes.length}
                </Text>
              </Stack>
              <Stack gap={4}>
                <Text size="xs" c="dimmed">Aprobados</Text>
                <Text size="lg" fw={600} c="emerald">
                  {postulantes.filter(
                    p => p.estado === 'seleccionado'
                  ).length}
                </Text>
              </Stack>
            </Stack>
          </Card>
        </Grid.Col>
      </Grid>

      <Tabs defaultValue="candidatos" radius="lg">
        <Tabs.List>
          <Tabs.Tab
            value="candidatos"
            leftSection={<IconUsers size={14} />}
          >
            Candidatos ({postulantes.length})
          </Tabs.Tab>
          <Tabs.Tab
            value="criterios"
            leftSection={<IconClipboardList size={14} />}
          >
            Criterios de evaluación
          </Tabs.Tab>
          <Tabs.Tab
            value="ranking"
            leftSection={<IconChartBar size={14} />}
          >
            Ranking
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="candidatos">
          <Card withBorder radius="lg" p="lg" mt="sm">
            <Stack gap="md">
              <Group justify="space-between">
                <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                  style={{ letterSpacing: '0.05em' }}>
                  Candidatos inscritos
                </Text>
                {['publicada', 'en_proceso'].includes(
                  convocatoria.estado
                ) && (
                  <Button
                    size="xs"
                    color="emerald"
                    leftSection={<IconPlus size={13} />}
                    onClick={abrirModal}
                  >
                    Inscribir candidato
                  </Button>
                )}
              </Group>

              {cargandoPostulantes ? (
                <Skeleton height={100} radius="md" />
              ) : postulantes.length === 0 ? (
                <EmptyState
                  icon={IconUsers}
                  title="Sin candidatos"
                  description={
                    convocatoria.estado === 'borrador'
                      ? 'Publica la convocatoria para empezar a inscribir candidatos.'
                      : 'No hay candidatos inscritos aún.'
                  }
                />
              ) : (
                <SgthTable
                  records={postulantes}
                  columns={columnsPostulantes}
                  fetching={cargandoPostulantes}
                  minHeight={150}
                />
              )}
            </Stack>
          </Card>
        </Tabs.Panel>

        <Tabs.Panel value="criterios">
          <Card withBorder radius="lg" mt="sm">
            <TabCriterios
              convocatoriaId={convocatoriaId}
              editable={convocatoria.estado === 'borrador'}
            />
          </Card>
        </Tabs.Panel>

        <Tabs.Panel value="ranking">
          <Card withBorder radius="lg" mt="sm">
            <TabRanking
              convocatoriaId={convocatoriaId}
              estadoConvocatoria={convocatoria.estado}
            />
          </Card>
        </Tabs.Panel>
      </Tabs>

      <InscribirPostulanteModal
        opened={modalOpened}
        onClose={cerrarModal}
        convocatoriaId={convocatoriaId}
      />

      <CalificarPostulanteModal
        opened={calModalOpened}
        onClose={() => {
          cerrarCalModal()
          setPostulanteSel(null)
        }}
        postulante={postulanteSel}
        convocatoriaId={convocatoriaId}
      />
    </Stack>
  )
}
