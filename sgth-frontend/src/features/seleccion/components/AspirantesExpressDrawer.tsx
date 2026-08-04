'use client'

import { Badge, Drawer, Group, Select, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable } from '@/components/ui/SgthTable'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useAspirantesExpress } from '../hooks/useExpress'
import type { AspiranteExpress, FiltroAnios, TarjetaExpress } from '../services/expressService'

const ESTADO_LABELS: Record<string, string> = {
  inscrito: 'Inscrito',
  en_evaluacion: 'En evaluación',
  aprobado: 'Aprobado',
  reprobado: 'Reprobado',
  descalificado: 'Descalificado',
  seleccionado: 'Seleccionado',
  ganador_potencial: 'En evaluación médica',
  no_seleccionado: 'No seleccionado',
  lista_espera: 'Lista de espera',
  incorporado: 'Incorporado',
}

const ESTADO_COLORS: Record<string, string> = {
  inscrito: 'gray',
  en_evaluacion: 'blue',
  aprobado: 'emerald',
  reprobado: 'red',
  descalificado: 'red',
  seleccionado: 'emerald',
  ganador_potencial: 'cyan',
  no_seleccionado: 'gray',
  lista_espera: 'yellow',
  incorporado: 'violet',
}

function formatFecha(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

interface Props {
  opened: boolean
  onClose: () => void
  contenedor: TarjetaExpress | null
  filtro: FiltroAnios
  estado: string | null
  onEstadoChange: (estado: string | null) => void
}

export function AspirantesExpressDrawer({
  opened, onClose, contenedor, filtro, estado, onEstadoChange,
}: Props) {
  const { isMobile } = useMobileBreakpoint()

  const { data, isLoading } = useAspirantesExpress(
    opened && contenedor ? contenedor.convocatoria_id : null,
    { ...filtro, ...(estado ? { estado } : {}) },
  )

  const aspirantes = data?.data ?? []

  const columns: DataTableColumn<AspiranteExpress>[] = [
    {
      accessor: 'aspirante',
      title: 'Aspirante',
      render: (a) => (
        <div>
          <Text size="sm" fw={500}>
            {[a.apellidos, a.segundo_apellido, a.nombres, a.segundo_nombre]
              .filter(Boolean).join(' ')}
          </Text>
          <Text size="xs" c="dimmed">{a.cedula}</Text>
        </div>
      ),
    },
    {
      accessor: 'puesto',
      title: 'Puesto al que aspira',
      render: (a) => (
        <div>
          <Text size="sm">{a.puesto?.cargo?.nombre ?? '—'}</Text>
          <Text size="xs" c="dimmed">
            {a.puesto?.unidad_administrativa?.nombre ?? '—'}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'fecha_inscripcion',
      title: 'Inscripción',
      width: 120,
      render: (a) => <Text size="sm">{formatFecha(a.fecha_inscripcion)}</Text>,
    },
    {
      accessor: 'evaluacion',
      title: 'Puntaje',
      width: 90,
      render: (a) => (
        <Text size="sm">
          {a.evaluacion?.puntaje_total != null
            ? Number(a.evaluacion.puntaje_total).toFixed(2)
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 160,
      render: (a) => (
        <Badge color={ESTADO_COLORS[a.estado] ?? 'gray'} variant="light" size="sm">
          {ESTADO_LABELS[a.estado] ?? a.estado}
        </Badge>
      ),
    },
  ]

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      position="right"
      size={isMobile ? '100%' : 'xl'}
      title={contenedor?.titulo ?? 'Aspirantes'}
    >
      <Stack gap="md">
        <Group>
          <Select
            label="Filtrar por estado"
            placeholder="Todos"
            data={Object.keys(ESTADO_LABELS).map((e) => ({
              value: e, label: ESTADO_LABELS[e],
            }))}
            value={estado}
            onChange={onEstadoChange}
            clearable
            style={{ minWidth: 220 }}
          />
          <Text size="sm" c="dimmed" mt="lg">
            {data?.total ?? aspirantes.length} aspirante(s)
          </Text>
        </Group>

        <SgthTable
          records={aspirantes}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      </Stack>
    </Drawer>
  )
}
