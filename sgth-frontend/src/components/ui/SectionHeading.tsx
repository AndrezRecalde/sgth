import { Group, Text } from '@mantine/core'
import classes from './SectionHeading.module.css'

interface Props {
  /** El nombre de la sección. Se pinta como encabezado de verdad (`h3`). */
  title: string
  /** Icono a la izquierda del título. Decorativo: el nombre ya va en el texto. */
  icon?: React.ReactNode
  /**
   * Un control junto al título —agregar, desplegar—. Va **fuera** del
   * encabezado: un botón dentro de un título no se puede etiquetar aparte.
   */
  action?: React.ReactNode
}

/**
 * Título de una sección dentro de un panel o un cajón.
 *
 * Es el hermano compacto de `SectionCard`: misma jerarquía (`h3`), sin la
 * tarjeta. `SectionCard` delimita bloques de una página; donde el espacio no
 * da para anidar tarjetas —una barra lateral estrecha, un cajón de detalle—
 * va este.
 *
 * Sustituye a `<Divider label={...}>`, que dibujaba lo mismo pero lo contaba
 * al revés: el `Divider` de Mantine es un `role="separator"`, así que el
 * nombre de la sección se anunciaba como el texto de una línea divisoria y no
 * como un encabezado. Nada indicaba dónde empieza «Alergias»; y cuando la
 * etiqueta llevaba dentro el botón de «+», ese botón quedaba dentro del
 * separador. Aquí el encabezado es un encabezado, el botón queda al lado, y
 * la línea es decoración declarada como tal.
 */
export function SectionHeading({ title, icon, action }: Props) {
  return (
    <Group gap={6} wrap="nowrap" align="center">
      {icon}
      <Text
        component="h3"
        size="xs"
        fw={600}
        tt="uppercase"
        c="dimmed"
        className={classes.titulo}
      >
        {title}
      </Text>
      {action}
      <span aria-hidden className={classes.linea} />
    </Group>
  )
}
