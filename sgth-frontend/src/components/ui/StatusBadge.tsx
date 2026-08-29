import { Badge } from '@mantine/core'
import type { MantineSize } from '@mantine/core'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'

interface Props {
  children: React.ReactNode
  tone?: SemanticTone
  size?: MantineSize
}

/**
 * Etiqueta de estado.
 *
 * Existe para que el significado ("esto salió mal") y el color (rojo) se
 * decidan en un solo sitio. Antes cada módulo declaraba su propio
 * `ESTADO_*_COLORS` con nombres de color de Mantine dentro, y el mismo
 * concepto acababa en tonos distintos según la pantalla.
 *
 * Los mapas de estado de cada módulo pasan a devolver un `SemanticTone`:
 *
 *   const TONO_SOLICITUD: Record<EstadoSolicitud, SemanticTone> = {
 *     aprobada:  'success',
 *     pendiente: 'warning',
 *     negada:    'danger',
 *   }
 *
 *   <StatusBadge tone={TONO_SOLICITUD[s.estado]}>{s.estado_label}</StatusBadge>
 */
export function StatusBadge({ children, tone = 'neutral', size = 'sm' }: Props) {
  return (
    <Badge variant="light" color={SEMANTIC_COLOR[tone]} size={size} radius="sm">
      {children}
    </Badge>
  )
}
