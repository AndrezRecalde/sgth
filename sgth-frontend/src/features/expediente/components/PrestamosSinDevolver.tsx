import { Text } from '@mantine/core'
import { formatFechaHora } from '@/lib/fecha'
import type { PasoDeLaDesvinculacion } from '../services/desvinculacionService'

/**
 * Los equipos de préstamo de TIC que el servidor se llevó y no ha devuelto:
 * dejan pendiente el paso de los bienes. La devolución la registra TIC en
 * Préstamos, no aquí.
 */
export function PrestamosSinDevolver({ prestamos }: { prestamos: NonNullable<PasoDeLaDesvinculacion['prestamos']> }) {
  return prestamos.map((p) => (
    <Text key={p.prestamo_id} size="xs">
      Préstamo {p.folio} ({p.equipos}) · {p.vencido ? 'debía volver' : 'vuelve'} el {formatFechaHora(p.fin_previsto)}.
      La devolución la registra TIC en Préstamos.
    </Text>
  ))
}
