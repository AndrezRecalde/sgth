'use client'

import { confirmar, DataState, PAGINACION_ES, SgthModal, SgthTable } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Group, TextInput, NumberInput, Button,
  ActionIcon, Text, Select, Alert,
} from '@mantine/core'
import { IconTrash, IconPlus, IconClock, IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useHorasTrabajadas, useHorasTrabajadasMutations } from '../hooks/useHorasTrabajadas'
import type { UnidadConRelaciones } from '@/types/api'
import type { HorasTrabajadasPeriodo } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function GestionarHorasTrabajadasModal({ opened, onClose }: Props) {
  const contained = useContainedInput()

  // La tabla estaba sin paginador contra un endpoint que pagina de 15 en 15:
  // el registro 16 y los siguientes existían, contaban para los índices y no
  // había forma de verlos ni de borrarlos. Con carga mensual son 15 meses.
  const [page, setPage] = useState(1)
  const [periodo, setPeriodo] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)
  const [totalHoras, setTotalHoras] = useState<number | ''>('')

  const { data, isLoading, error, refetch } = useHorasTrabajadas({ page })
  const registros = data?.data ?? []
  const { registrar, eliminar } = useHorasTrabajadasMutations()
  const { data: unidades = [], error: errorUnidades } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = ((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
    value: String(u.id), label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const handleRegistrar = () => {
    if (!periodo || !totalHoras) return
    registrar.mutate({
      periodo,
      unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
      total_horas: Number(totalHoras),
    }, {
      onSuccess: () => {
        setPeriodo('')
        setUnidadId(null)
        setTotalHoras('')
        // El listado va por período descendente: lo que se acaba de cargar
        // aparece en la primera página, no en la que se esté mirando.
        setPage(1)
      },
    })
  }

  const columns: DataTableColumn<HorasTrabajadasPeriodo>[] = [
    { accessor: 'periodo', title: 'Período', width: 110 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (r) => r.unidad_administrativa?.nombre ?? 'Total institucional',
    },
    {
      accessor: 'total_horas',
      title: 'Horas',
      width: 110,
      render: (r) => r.total_horas.toLocaleString(),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => (
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => confirmar({
            title:   'Eliminar registro de horas',
            message: 'Se eliminará este registro de horas trabajadas. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => eliminar.mutate(r.id),
          })}
        >
          <IconTrash size={16} />
        </ActionIcon>
      ),
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Horas trabajadas por período"
      size="lg"
    >
      <Stack gap="md">
        <Text size="xs" c="dimmed">
          Cargue manualmente el total de horas trabajadas por período (formato AAAA para un año, o AAAA-MM para
          un mes). Deje la unidad en blanco para registrar el total institucional. Si carga los meses y consulta
          el año, los índices CD 513 suman los meses cargados; el total institucional manda sobre las unidades.
        </Text>
        {/* El fallo del catálogo va en un aviso y no en el campo: la fila es un
            `Group` alineado abajo y sin envolver, así que un error debajo del
            Select desalinea los otros dos campos. Además no es un error de lo
            que se escribió, sino del dato que hay detrás, y aquí hay algo que
            se puede hacer igual. */}
        {errorUnidades && (
          <Alert icon={<IconAlertTriangle size={16} />} color="amber" variant="light">
            No se pudieron cargar las unidades administrativas. Puede registrar el total
            institucional dejando la unidad en blanco.
          </Alert>
        )}

        <Group align="flex-end" wrap="nowrap">
          <TextInput
            label="Período"
            placeholder="2026 o 2026-07"
            style={{ flex: 1 }}
            {...contained}
            value={periodo}
            onChange={(e) => setPeriodo(e.currentTarget.value)}
          />
          <Select
            label="Unidad (opcional)"
            placeholder="Total institucional"
            data={unidadOptions}
            searchable
            clearable
            style={{ flex: 1 }}
            {...contained}
            value={unidadId}
            onChange={setUnidadId}
          />
          <NumberInput
            label="Total de horas"
            min={1}
            style={{ width: 150 }}
            {...contained}
            value={totalHoras}
            onChange={(v) => setTotalHoras(typeof v === 'number' ? v : '')}
          />
          <Button
            leftSection={<IconPlus size={16} />}
            loading={registrar.isPending}
            onClick={handleRegistrar}
            disabled={!periodo || !totalHoras}
          >
            Guardar
          </Button>
        </Group>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudieron cargar las horas trabajadas"
          errorHint="No quiere decir que no haya períodos cargados: no se pudieron consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!registros.length}
          page={page}
          emptyProps={{
            icon: IconClock,
            title: 'Sin registros de horas trabajadas',
            description: 'Cargue el total de horas del período con el formulario de arriba: es el denominador de los índices CD 513.',
          }}
        >
          <SgthTable
            {...PAGINACION_ES}
            records={registros}
            columns={columns}
            totalRecords={data?.total ?? 0}
            recordsPerPage={15}
            page={page}
            onPageChange={setPage}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
