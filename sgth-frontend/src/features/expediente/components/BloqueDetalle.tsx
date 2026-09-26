import { Paper } from '@mantine/core'

interface Props {
  children: React.ReactNode
  /** Fondo hundido: distingue lo que YA ES de lo que se propone. */
  hundido?: boolean
  /** Iguala la altura con el bloque de al lado dentro de una rejilla. */
  altoCompleto?: boolean
}

/**
 * Bloque compacto de un cajón o un panel del Expediente.
 *
 * `SectionCard` es el equivalente de una página —tarjeta grande, `Title`— y
 * aquí queda enorme: estos bloques se apilan de cuatro en cuatro dentro de un
 * cajón de 560 px. El título va aparte, con `SectionHeading`.
 *
 * Existe porque el mismo `<Paper withBorder p="sm" radius="md">` estaba
 * copiado trece veces en el módulo, y bastaba que alguien tocara uno para que
 * dejaran de parecerse.
 */
export function BloqueDetalle({ children, hundido, altoCompleto }: Props) {
  return (
    <Paper
      withBorder
      p="sm"
      radius="md"
      h={altoCompleto ? '100%' : undefined}
      bg={hundido ? 'var(--sgth-surface-sunken)' : undefined}
    >
      {children}
    </Paper>
  )
}
