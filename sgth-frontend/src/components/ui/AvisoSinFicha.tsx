import { Alert, Text } from '@mantine/core'
import { IconUserOff } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { EmptyState } from './EmptyState'

const TITULO = 'Su usuario no está vinculado a un servidor'
const QUE_HACER = 'Pídale a Talento Humano que complete la vinculación.'

interface Props {
  /** Qué sale de la ficha en esta pantalla, en una frase. */
  motivo: string
  /**
   * Un aviso sobre la pantalla en vez del vacío: para la que sigue sirviendo
   * para consultar, como la disponibilidad de equipos.
   */
  compacto?: boolean
}

/**
 * Lo que ve en el Portal una cuenta sin ficha de servidor.
 *
 * Cada pantalla lo resolvía a su manera: un error 422 en rojo, un vacío que
 * decía «todavía no tiene…», o un formulario que fallaba después de llenarlo
 * (docs/plan-roles-y-accesos.md, PR 15). Ahora todas dicen lo mismo: por qué
 * no hay nada y a quién pedírselo.
 */
export function AvisoSinFicha({ motivo, compacto = false }: Props) {
  const texto = `${motivo} ${QUE_HACER}`

  if (compacto) {
    return (
      <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconUserOff size={16} />} title={TITULO}>
        <Text size="sm">{texto}</Text>
      </Alert>
    )
  }

  return <EmptyState icon={IconUserOff} title={TITULO} description={texto} />
}
