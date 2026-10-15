import type { Metadata } from 'next'
import { MiCredencialView } from './MiCredencialView'

export const metadata: Metadata = {
  title: 'Mi credencial',
  description: 'Su credencial institucional digital, con el código para verificarla',
}

export default function MiCredencialPage() {
  return <MiCredencialView />
}
