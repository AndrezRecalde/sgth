import classes from './PageShell.module.css'

interface Props {
  children: React.ReactNode
  /**
   * Quita el ancho máximo. Solo para lienzos que necesitan la ventana entera:
   * organigrama, odontograma, calendarios. Nunca para páginas de listado.
   */
  fluid?: boolean
}

/**
 * Contenedor de TODA página del área autenticada.
 *
 * Centraliza tres decisiones que antes tomaba cada página por su cuenta:
 * el ancho máximo de lectura, el padding (menor en móvil) y la separación
 * vertical entre bloques. Por eso `AppShell` tiene `padding={0}`: el aire lo
 * pone este componente, no el shell.
 *
 * Uso:
 *   <PageShell>
 *     <PageHeader title="Nómina" actions={...} />
 *     <Toolbar>...</Toolbar>
 *     <SgthTable ... />
 *   </PageShell>
 */
export function PageShell({ children, fluid = false }: Props) {
  return (
    <div className={`${classes.shell} ${fluid ? classes.full : ''}`}>
      {children}
    </div>
  )
}
