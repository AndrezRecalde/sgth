import { Badge, type BadgeProps } from '@mantine/core'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'

type Props = Omit<BadgeProps, 'color' | 'variant' | 'circle' | 'gradient'> & {
  /** Qué significa. Sin tono, la etiqueta es neutra. */
  tone?: SemanticTone
  /** `dot` para una marca discreta; `outline` para códigos; `filled` solo para alertas. */
  variant?: 'light' | 'dot' | 'outline' | 'filled'
}

/**
 * Toda etiqueta del sistema: estados, señales y categorías.
 *
 * Existe para que el significado ("esto salió mal") y el color (rojo) se
 * decidan en un solo sitio. Antes cada módulo declaraba su propio
 * `ESTADO_*_COLORS` con nombres de color de Mantine dentro, y el mismo
 * concepto acababa en tonos distintos según la pantalla. «Anulado» salía en
 * rojo en el odontograma y en naranja en los certificados.
 *
 * El color se elige por significado:
 *  - un estado o una señal lleva tono (`success`, `warning`, `danger`, `info`);
 *  - una categoría —tipo de permiso, régimen, especialidad, parentesco— no
 *    significa nada bueno ni malo, así que va neutra y se distingue por el texto.
 *
 * Los mapas de estado de cada módulo devuelven un `SemanticTone`:
 *
 *   const TONO_SOLICITUD: Record<EstadoSolicitud, SemanticTone> = {
 *     aprobada:  'success',
 *     pendiente: 'warning',
 *     negada:    'danger',
 *   }
 *
 *   <StatusBadge tone={TONO_SOLICITUD[s.estado]}>{s.estado_label}</StatusBadge>
 *   <StatusBadge>{TIPO_LABELS[p.tipo]}</StatusBadge>
 */
export function StatusBadge({ tone = 'neutral', variant = 'light', size = 'sm', ...props }: Props) {
  return (
    <Badge
      {...props}
      variant={variant}
      color={SEMANTIC_COLOR[tone]}
      size={size}
      radius="sm"
    />
  )
}
