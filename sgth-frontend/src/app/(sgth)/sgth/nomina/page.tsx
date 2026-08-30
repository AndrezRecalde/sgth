import type { Metadata } from 'next'
import { NominaView } from './NominaView'
export const metadata: Metadata = { title: 'Nómina' }
export default function NominaPage() { return <NominaView /> }
