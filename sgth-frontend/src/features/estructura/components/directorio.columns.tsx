import { Badge, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import type { ExtensionTelefonica } from '@/types/api'

export const directorioColumns: DataTableColumn<ExtensionTelefonica>[] = [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (record) => {
      const servidor = (record as any).servidor
      const nombre = servidor ? `${servidor.nombres} ${servidor.apellidos}` : 'No asignado'
      return <Text size="sm">{nombre}</Text>
    }
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad',
    render: (record) => {
      const nombre = (record as any).unidad_administrativa?.nombre || '-'
      return <Text size="sm">{nombre}</Text>
    }
  },
  {
    accessor: 'numero_extension',
    title: 'Extensión',
    render: (record) => (
      <Badge color="emerald" variant="light">
        Ext. {(record as any).numero_extension}
      </Badge>
    )
  },
  {
    accessor: 'telefono',
    title: 'Teléfono',
    render: (record) => {
      const servidor = (record as any).servidor
      const tel = servidor?.telefono_institucional || servidor?.telefono_celular || '-'
      return <Text size="sm">{tel}</Text>
    }
  },
  {
    accessor: 'email',
    title: 'Email',
    render: (record) => {
      const email = (record as any).servidor?.correo_institucional || '-'
      return <Text size="sm">{email}</Text>
    }
  }
]
