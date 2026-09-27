import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { EmisionCertificado } from '../services/certificadoLaboralService'

export const getCertificadosEmitidosColumns =
  (): DataTableColumn<EmisionCertificado>[] => [
    {
      accessor: 'codigo',
      title: 'Certificado',
      render: (e) => (
        <div>
          {/* `outline` es el tratamiento de los códigos en todo el sistema. */}
          <StatusBadge variant="outline" size="xs">
            {e.codigo}
          </StatusBadge>
          <Text size="xs" c="dimmed" mt={4}>
            {e.tipo_titulo}
            {e.con_remuneracion ? ' · con remuneración' : ' · sin remuneración'}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'emitido_en',
      title: 'Emitido',
      width: 170,
      render: (e) => (
        <div>
          {/* El backend manda 'Y-m-d H:i', no ISO: se parte en vez de pasarlo
              por `formatFechaHora`, que en una columna estrecha dejaba
              «12:48 a. m.» cortado en dos líneas. */}
          <Text size="sm">{formatFecha(e.emitido_en?.slice(0, 10))}</Text>
          {/* La hora y quién lo emitió: esto es también un registro de acceso
              a datos personales, y sin el nombre solo responde a la mitad. */}
          <Text size="xs" c="dimmed">
            {[e.emitido_en?.slice(11, 16), e.emitido_por]
              .filter(Boolean)
              .join(' · ')}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'vence_en',
      title: 'Vigencia',
      width: 150,
      render: (e) =>
        e.vigente ? (
          <Text size="sm">Hasta {formatFecha(e.vence_en)}</Text>
        ) : (
          // Vencido no es anulado: el certificado se emitió y su código sigue
          // verificando. Lo que caducó es su validez, y por eso va en ámbar y
          // no en rojo.
          <StatusBadge tone="warning" size="xs">
            Vencido el {formatFecha(e.vence_en)}
          </StatusBadge>
        ),
    },
  ]
