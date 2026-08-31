import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Stack, Group, Text, Badge, Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash, IconSchool, IconEdit } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useHistorialAcademico } from '../../hooks/useHistorialAcademico'
import { useHistorialAcademicoMutations } from '../../hooks/useHistorialAcademicoMutations'
import { HistorialAcademicoModal } from '../HistorialAcademicoModal'
import type { HistorialAcademicoServidor } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

interface Props { servidorId: number }

export function AcademicoTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [editItem, setEditItem] = useState<HistorialAcademicoServidor | null>(null)
  const { data: historial = [], isLoading } = useHistorialAcademico(servidorId)
  const { eliminar } = useHistorialAcademicoMutations(servidorId)

  const columns: DataTableColumn<HistorialAcademicoServidor>[] = [
    {
      accessor: 'titulo_capacitacion',
      title: 'Título / Capacitación',
      render: ({ titulo_capacitacion, nivel_estudio }) => (
        <div>
          <Text size="sm" fw={500}>{titulo_capacitacion ?? '-'}</Text>
          {nivel_estudio && (
            <Text size="xs" c="dimmed">
              {nivel_estudio === 'tercer_nivel' ? 'Tercer Nivel (Pregrado)' :
               nivel_estudio === 'cuarto_nivel' ? 'Cuarto Nivel (Posgrado)' :
               nivel_estudio.charAt(0).toUpperCase() + nivel_estudio.slice(1)}
            </Text>
          )}
        </div>
      ),
    },
    {
      accessor: 'institucion',
      title: 'Institución',
      render: ({ institucion, nacionalidad_estudio }) => (
        <div>
          <Text size="sm">{institucion ?? '-'}</Text>
          <Text size="xs" c="dimmed">
            {nacionalidad_estudio === 'nacional' ? 'Nacional' : 'Internacional'}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'tipo_estudio',
      title: 'Tipo',
      width: 120,
      render: ({ tipo_estudio }) => (
        <Badge
          color={tipo_estudio === 'estudio' ? 'emerald' : 'blue'}
          variant="light"
          size="xs"
        >
          {tipo_estudio === 'estudio' ? 'Título Académico' : 'Capacitación'}
        </Badge>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Período',
      width: 150,
      render: ({ fecha_inicio, fecha_fin }) => {
        const getYear = (dStr: string) => dStr.split('T')[0].split('-')[0]
        const yStart = fecha_inicio ? getYear(fecha_inicio) : '-'
        const yEnd = fecha_fin ? getYear(fecha_fin) : 'Presente'
        return <Text size="sm">{`${yStart} - ${yEnd}`}</Text>
      },
    },
    {
      accessor: 'codigo_senescyt',
      title: 'SENESCYT',
      width: 120,
      render: ({ codigo_senescyt }) => (
        <Text size="sm" ff="monospace">{codigo_senescyt || '-'}</Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (item) => (
        <TableActions actions={[
          {
            label: 'Editar',
            icon: <IconEdit size={14} />,
            color: 'blue',
            onClick: () => { setEditItem(item); open() },
          },
          {
            label: 'Eliminar',
            icon: <IconTrash size={14} />,
            color: 'red',
            onClick: () => confirmar({
              title:   'Eliminar registro académico',
              message: 'Se eliminará este registro académico del expediente. No se puede deshacer.',
              destructiva: true,
              onConfirm: () => eliminar.mutate(Number(item.id)),
            }),
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Agregar registro
        </Button>
      </Group>
      {!isLoading && (historial as HistorialAcademicoServidor[]).length === 0 ? (
        <EmptyState
          icon={IconSchool}
          title="Sin historial académico"
          description="Registra los títulos académicos o capacitaciones del servidor."
        />
      ) : (
        <SgthTable
          records={historial as HistorialAcademicoServidor[]}
          columns={columns}
          fetching={isLoading}
          minHeight={100}
        />
      )}
      <HistorialAcademicoModal
        opened={opened}
        onClose={() => { setEditItem(null); close() }}
        servidorId={servidorId}
        initialValues={editItem}
      />
    </Stack>
  )
}
