import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { SITUACION_CREDENCIAL } from '../constants/credenciales'
import type { ServidorConCredencial } from '../services/tiposCredencial'
import { FotoServidor } from './FotoServidor'

/** La línea que explica la situación, debajo de su etiqueta. */
function detalle(s: ServidorConCredencial): string | null {
  const c = s.credencial

  switch (s.situacion) {
    case 'sin_foto':
      return 'Tómele la foto en Fotografías'
    case 'pendiente':
      return 'Ya tiene foto: la credencial se crea esta noche'
    case 'vencida':
      return c?.valida_hasta ? `Venció el ${formatFecha(c.valida_hasta)}` : null
    case 'sin_imprimir':
      return c?.valida_hasta ? `Válida hasta el ${formatFecha(c.valida_hasta)}` : 'Nunca se imprimió'
    case 'desactualizada':
      return `Cambió: ${c?.cambios_desde_impresion.join(', ').toLowerCase()}`
    case 'al_dia':
      return c?.ultima_impresion ? `El ${formatFecha(c.ultima_impresion.impresa_en)}` : null
    case 'por_recibir':
      return 'Ya no trabaja aquí y no ha devuelto la tarjeta'
  }
}

/** Los servidores con su credencial, en la pantalla Credenciales (fase 3.2). */
export function columnasCredenciales(
  accionesDe: (s: ServidorConCredencial) => TableAction[],
): DataTableColumn<ServidorConCredencial>[] {
  return [
    {
      accessor: 'foto',
      title: '',
      width: 60,
      render: (s) => (
        <FotoServidor fotografiaId={s.fotografia?.id} nombre={s.nombre_completo} size={40} radius="xl" />
      ),
    },
    {
      accessor: 'nombre_completo',
      title: 'Servidor',
      render: (s) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{s.nombre_completo}</Text>
          <Text size="xs" c="dimmed">{s.cedula}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'unidad',
      title: 'Unidad y cargo',
      render: (s) => (
        <Stack gap={0}>
          <Text size="sm">{s.unidad?.nombre ?? 'Sin unidad'}</Text>
          <Text size="xs" c="dimmed">{s.cargo ?? 'Sin puesto asignado'}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'situacion',
      title: 'Credencial',
      width: 230,
      render: (s) => {
        const situacion = SITUACION_CREDENCIAL[s.situacion]
        const linea = detalle(s)

        return (
          <Stack gap={2}>
            <StatusBadge tone={situacion.tono} size="xs">{situacion.etiqueta}</StatusBadge>
            {linea && <Text size="xs" c="dimmed">{linea}</Text>}
          </Stack>
        )
      },
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => <TableActions actions={accionesDe(s)} />,
    },
  ]
}
