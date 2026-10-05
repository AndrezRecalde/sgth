'use client'

import { TONO_POSTULANTE } from '@/features/seleccion/services/convocatoriaService'
import { useState } from 'react'
import { Stack, Group, Text, Button, Card, Grid, Tabs, Divider, ThemeIcon, Skeleton } from '@mantine/core'
import {
  IconArrowLeft,
  IconUsers,
  IconWorldUpload,
  IconCalendar,
  IconPlus,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { TabRanking } from '@/features/seleccion/components/TabRanking'
import { CerrarConvocatoriaAcciones } from '@/features/seleccion/components/CerrarConvocatoriaAcciones'
import { PerfilPostulanteDrawer } from '@/features/seleccion/components/PerfilPostulanteDrawer'
import { IconChartBar } from '@tabler/icons-react'
import {
  useConvocatoriaDetalle,
  usePostulantes,
  usePublicarConvocatoria,
} from '@/features/seleccion/hooks/useConvocatoria'
import {
  TONO_CONVOCATORIA,
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
import { formatFechaMes } from '@/lib/fecha'

import { confirmar, EmptyState, PageHeader, PageShell, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'

interface Props {
  id: string
}

const ESTADOS_APROBADOS = ['aprobado', 'lista_espera', 'ganador_potencial', 'seleccionado', 'incorporado']

export function DetalleConvocatoriaView({ id }: Props) {
  const convocatoriaId = Number(id)
  const router       = useRouter()
  const publicar = usePublicarConvocatoria()
  // El analista ve y califica; gestionar es de admin-uath (2026-10-05).
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-convocatorias')
  const califica = hasPermiso('evaluar-postulantes')
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const [postulanteSel, setPostulanteSel] =
    useState<Postulante | null>(null)
  const [perfilId, setPerfilId] = useState<number | null>(null)
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
        <StatusBadge tone={TONO_POSTULANTE[p.estado] ?? 'neutral'}>
          {getLabelEstadoPostulante(p.estado)}
        </StatusBadge>
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
            hidden:  !califica,
            label:   p.evaluacion ? 'Editar calificación' : 'Calificar',
            icon:    p.evaluacion
              ? <IconEdit size={14} />
              : <IconStar size={14} />,
            onClick: () => {
              setPostulanteSel(p)
              abrirCalModal()
            },
          },
          {
            label:   'Ver perfil',
            icon:    <IconUsers size={14} />,
            // Panel lateral (2026-10-05): la página de perfil no existía.
            onClick: () => setPerfilId(p.id),
          },
        ]} />
      ),
    },
  ]

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={80} radius="lg" />
        <Skeleton height={200} radius="lg" />
      </PageShell>
    )
  }

  // Antes devolvía null: una convocatoria inexistente dejaba la página en blanco.
  if (!convocatoria) {
    return (
      <PageShell>
        <PageHeader title="Convocatoria" onBack={() => router.push(ROUTES.SGTH.CONVOCATORIAS)} />
        <EmptyState
          icon={IconClipboardList}
          title="Convocatoria no encontrada"
          description="No existe o no está disponible. Vuelva al listado y ábrala desde allí."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title={convocatoria.titulo}
        description={convocatoria.codigo}
        actions={
          <Group gap="xs">
            <Button
              variant="default"
              leftSection={<IconArrowLeft size={14} />}
              onClick={() =>
                router.push(ROUTES.SGTH.CONVOCATORIAS)
              }
            >
              Volver
            </Button>
            {gestiona && convocatoria.estado === 'borrador' && (
              <Button
                variant="default"
                leftSection={<IconEdit size={14} />}
                onClick={() => router.push(ROUTES.SGTH.CONVOCATORIA_EDITAR(convocatoriaId))}
              >
                Editar
              </Button>
            )}
            {gestiona && convocatoria.estado === 'borrador' && (
              <Button
                leftSection={<IconWorldUpload size={14} />}
                loading={publicar.isPending}
                onClick={() => confirmar({
                  title:   'Publicar convocatoria',
                  message: 'La convocatoria quedará visible para los postulantes.',
                  confirmLabel: 'Publicar',
                  onConfirm: () => publicar.mutate(convocatoriaId),
                })}
              >
                Publicar convocatoria
              </Button>
            )}
            {gestiona && convocatoria.estado === 'publicada' && (
              <CerrarConvocatoriaAcciones
                convocatoriaId={convocatoriaId}
                codigo={convocatoria.codigo}
                hayAprobados={postulantes.some((p) => p.estado === 'aprobado')}
              />
            )}
            {/* Sin «Declarar ganador oficial» (2026-10-04): no miraba el
                dictamen ni creaba el expediente. Cada ganador apto se
                incorpora desde el Ranking, y con el último la convocatoria
                se finaliza sola. */}
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
                    <StatusBadge>
                      {getLabelTipo(convocatoria.tipo)}
                    </StatusBadge>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={6}>
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">Período</Text>
                    <Group gap="xs">
                      <ThemeIcon
                        size="xs"
                        variant="subtle"
                      >
                        <IconCalendar size={12} />
                      </ThemeIcon>
                      <Text size="sm">
                        {formatFechaMes(convocatoria.fecha_inicio)}
                        {' — '}
                        {formatFechaMes(convocatoria.fecha_fin)}
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
              <StatusBadge
                tone={TONO_CONVOCATORIA[convocatoria.estado] ?? 'neutral'}
                size="lg"
              >
                {getLabelEstado(convocatoria.estado)}
              </StatusBadge>
              {convocatoria.motivo_cierre && (
                <Text size="xs" c="dimmed">Motivo: {convocatoria.motivo_cierre}</Text>
              )}
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
                  {/* Los que superaron la evaluación, estén donde estén
                      después: antes contaba solo `seleccionado`, un estado
                      al que ya no lleva ninguna acción, y marcaba 0. */}
                  {postulantes.filter(
                    p => ESTADOS_APROBADOS.includes(p.estado)
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
                {gestiona && convocatoria.estado === 'publicada' && (
                  <Button
                    size="xs"
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
                      ? 'Publique la convocatoria para empezar a inscribir candidatos.'
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
              editable={gestiona && convocatoria.estado === 'borrador'}
            />
          </Card>
        </Tabs.Panel>

        <Tabs.Panel value="ranking">
          <Card withBorder radius="lg" mt="sm">
            <TabRanking
              convocatoriaId={convocatoriaId}
              estadoConvocatoria={convocatoria.estado}
              vacantes={convocatoria.vacantes}
              puedeGestionar={gestiona}
            />
          </Card>
        </Tabs.Panel>
      </Tabs>

      <InscribirPostulanteModal
        opened={modalOpened}
        onClose={cerrarModal}
        convocatoriaId={convocatoriaId}
      />

      <PerfilPostulanteDrawer
        convocatoriaId={convocatoriaId}
        postulanteId={perfilId}
        onClose={() => setPerfilId(null)}
        puedeGestionar={gestiona}
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
    </PageShell>
  )
}