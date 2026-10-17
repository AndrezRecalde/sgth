import { IconFileTypePdf, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions, confirmar } from '@/components/ui'
import { getHistorialAcademicoColumns } from '@/features/expediente/components/historialAcademico.columns'
import type { HistorialAcademicoServidor } from '@/types/api'

interface Acciones {
  onDocumento: (id: number) => void
  onRetirar:   (id: number) => void
}

/**
 * La formación propia en «Mi perfil»: las columnas del expediente, con el
 * estado, y las acciones del servidor. Lo validado ya es del expediente: solo
 * Talento Humano lo corrige o lo quita.
 */
export function getMiFormacionColumns(
  { onDocumento, onRetirar }: Acciones,
): DataTableColumn<HistorialAcademicoServidor>[] {
  return [
    ...getHistorialAcademicoColumns(),
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => (
        <TableActions
          actions={[
            {
              label: 'Ver documento',
              icon: <IconFileTypePdf size={14} />,
              onClick: () => onDocumento(r.id),
              hidden: !r.tiene_documento,
            },
            {
              label: 'Retirar',
              icon: <IconTrash size={14} />,
              color: 'red',
              hidden: r.estado === 'validado',
              onClick: () => confirmar({
                title: 'Retirar registro',
                message: `«${r.titulo_capacitacion}» dejará de esperar la revisión de Talento Humano, y su documento se borra.`,
                confirmLabel: 'Retirar',
                destructiva: true,
                onConfirm: () => onRetirar(r.id),
              }),
            },
          ]}
        />
      ),
    },
  ]
}
