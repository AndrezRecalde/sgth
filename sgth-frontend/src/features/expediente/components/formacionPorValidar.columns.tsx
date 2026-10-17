import { Text } from '@mantine/core'
import { IconCheck, IconFileTypePdf, IconFolder, IconX } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { FormacionPorValidar } from '@/types/api'

interface Acciones {
  onDocumento:  (r: FormacionPorValidar) => void
  onValidar:    (r: FormacionPorValidar) => void
  onRechazar:   (r: FormacionPorValidar) => void
  onExpediente: (r: FormacionPorValidar) => void
}

const NIVEL: Record<string, string> = {
  primaria: 'Primaria', secundaria: 'Secundaria',
  tercer_nivel: 'Tercer nivel', cuarto_nivel: 'Cuarto nivel',
}

const anio = (fecha?: string | null) => (fecha ? fecha.substring(0, 4) : null)

/** La bandeja de Talento Humano: de quién es cada título y con qué se valida. */
export function getFormacionPorValidarColumns(a: Acciones): DataTableColumn<FormacionPorValidar>[] {
  return [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: ({ servidor }) => (
        <div>
          <Text size="sm" fw={500}>
            {[servidor.apellido, servidor.segundo_apellido, servidor.nombre].filter(Boolean).join(' ')}
          </Text>
          <Text size="xs" c="dimmed">
            {servidor.cedula}
            {servidor.unidad_administrativa?.nombre ? ` · ${servidor.unidad_administrativa.nombre}` : ''}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'titulo_capacitacion',
      title: 'Título / Capacitación',
      render: ({ titulo_capacitacion, tipo_estudio, nivel_estudio }) => (
        <div>
          <Text size="sm">{titulo_capacitacion}</Text>
          <Text size="xs" c="dimmed">
            {tipo_estudio === 'estudio' ? (NIVEL[nivel_estudio ?? ''] ?? 'Título académico') : 'Capacitación'}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'institucion',
      title: 'Institución',
      render: ({ institucion, nacionalidad_estudio }) => (
        <div>
          <Text size="sm">{institucion}</Text>
          {nacionalidad_estudio === 'internacional' && <StatusBadge size="xs">Exterior</StatusBadge>}
        </div>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Período',
      width: 120,
      render: ({ fecha_inicio, fecha_fin }) => (
        <Text size="sm">{`${anio(fecha_inicio) ?? '—'} – ${anio(fecha_fin) ?? 'En curso'}`}</Text>
      ),
    },
    {
      accessor: 'codigo_senescyt',
      title: 'SENESCYT',
      width: 150,
      render: ({ codigo_senescyt }) => (
        <Text size="sm" ff="monospace">{codigo_senescyt || '—'}</Text>
      ),
    },
    {
      accessor: 'created_at',
      title: 'Registrado',
      width: 110,
      render: ({ created_at }) => <Text size="sm">{formatFecha(created_at)}</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => (
        <TableActions
          actions={[
            { label: 'Ver documento', icon: <IconFileTypePdf size={14} />, onClick: () => a.onDocumento(r) },
            { label: 'Validar', icon: <IconCheck size={14} />, onClick: () => a.onValidar(r) },
            { label: 'Rechazar', icon: <IconX size={14} />, color: 'red', onClick: () => a.onRechazar(r) },
            { label: 'Abrir expediente', icon: <IconFolder size={14} />, onClick: () => a.onExpediente(r) },
          ]}
        />
      ),
    },
  ]
}
