'use client'

import { Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { useCondicionCargaMutations } from '../hooks/useCondicionCargaMutations'
import { TIPO_DISCAPACIDAD_LABELS } from '../utils/discapacidad'
import { DiscapacidadCargaFamiliarModal } from './DiscapacidadCargaFamiliarModal'
import { EnfermedadCargaFamiliarModal } from './EnfermedadCargaFamiliarModal'
import type {
  CargaFamiliar,
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
} from '@/types/api'

interface Props {
  carga: CargaFamiliar
  servidorId: number
}

/**
 * El detalle de las condiciones de un familiar, que se despliega bajo su fila.
 * Solo aparecen los bloques que la carga declara: sin discapacidad marcada no
 * hay nada que registrar ahí.
 */
export function CargaFamiliarCondiciones({ carga, servidorId }: Props) {
  const [discOpened, { open: abrirDisc, close: cerrarDisc }] = useDisclosure(false)
  const [enfOpened, { open: abrirEnf, close: cerrarEnf }] = useDisclosure(false)
  const { eliminarDiscapacidad, eliminarEnfermedad } =
    useCondicionCargaMutations(servidorId, Number(carga.id))

  const discapacidades = carga.discapacidades ?? []
  const enfermedades = carga.enfermedades_catastroficas ?? []

  const discColumns: DataTableColumn<DiscapacidadCargaFamiliar>[] = [
    {
      accessor: 'tipo_discapacidad',
      title: 'Tipo',
      render: (d) => (
        <Text size="sm">
          {TIPO_DISCAPACIDAD_LABELS[d.tipo_discapacidad] ?? d.tipo_discapacidad}
        </Text>
      ),
    },
    {
      accessor: 'porcentaje',
      title: '%',
      width: 70,
      render: (d) => <Text size="sm">{d.porcentaje}%</Text>,
    },
    {
      accessor: 'numero_carnet_conadis',
      title: 'Carnet CONADIS',
      render: (d) => (
        <Text size="sm" ff="monospace">{d.numero_carnet_conadis ?? '—'}</Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (d) => (
        <TableActions actions={[{
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar discapacidad',
            message: 'Se eliminará esta discapacidad de la carga familiar. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => eliminarDiscapacidad.mutate(d.id),
          }),
        }]} />
      ),
    },
  ]

  const enfColumns: DataTableColumn<EnfermedadCatastroficaCargaFamiliar>[] = [
    {
      accessor: 'tipo_enfermedad',
      title: 'Enfermedad',
      render: (e) => <Text size="sm">{e.tipo_enfermedad}</Text>,
    },
    {
      accessor: 'codigo_cie10',
      title: 'CIE-10',
      width: 90,
      render: (e) => (
        <Text size="sm" ff="monospace">{e.codigo_cie10 ?? '—'}</Text>
      ),
    },
    {
      accessor: 'fecha_diagnostico',
      title: 'Diagnóstico',
      width: 120,
      render: (e) => <Text size="sm">{formatFecha(e.fecha_diagnostico)}</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (e) => (
        <TableActions actions={[{
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar enfermedad',
            message: 'Se eliminará esta enfermedad de la carga familiar. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => eliminarEnfermedad.mutate(e.id),
          }),
        }]} />
      ),
    },
  ]

  return (
    <Stack gap="md" p="md">
      {carga.persona_con_discapacidad && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} tt="uppercase" c="dimmed">Discapacidades</Text>
            <Button size="xs" variant="subtle"
              leftSection={<IconPlus size={12} />} onClick={abrirDisc}>
              Agregar
            </Button>
          </Group>
          <SgthTable
            records={discapacidades}
            columns={discColumns}
            minHeight={60}
            noRecordsText="Sin discapacidades registradas aún."
          />
        </Stack>
      )}

      {carga.posee_enfermedad_catastrofica && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} tt="uppercase" c="dimmed">Enfermedades catastróficas</Text>
            <Button size="xs" variant="subtle"
              leftSection={<IconPlus size={12} />} onClick={abrirEnf}>
              Agregar
            </Button>
          </Group>
          <SgthTable
            records={enfermedades}
            columns={enfColumns}
            minHeight={60}
            noRecordsText="Sin enfermedades registradas aún."
          />
        </Stack>
      )}

      <DiscapacidadCargaFamiliarModal
        opened={discOpened}
        onClose={cerrarDisc}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />
      <EnfermedadCargaFamiliarModal
        opened={enfOpened}
        onClose={cerrarEnf}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />
    </Stack>
  )
}
