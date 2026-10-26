import type { Metadata } from 'next'
import { NominaView } from './NominaView'
export const metadata: Metadata = { title: 'Novedades del mes' }
export default function NominaPage() { return <NominaView /> }
