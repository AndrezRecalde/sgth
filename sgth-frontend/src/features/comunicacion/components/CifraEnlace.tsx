import Link from 'next/link'
import { StatCard } from '@/components/ui'
import classes from './CifraEnlace.module.css'

type Props = Omit<React.ComponentProps<typeof StatCard>, 'value'> & {
  value: number
  /** El listado donde están los que suman esta cifra. */
  href: string
}

/** Una cifra del Inicio que abre su listado ya filtrado. */
export function CifraEnlace({ href, ...cifra }: Props) {
  return (
    <Link href={href} className={classes.enlace} aria-label={`${cifra.label}: ${cifra.value}. Ver el listado`}>
      <StatCard {...cifra} />
    </Link>
  )
}
