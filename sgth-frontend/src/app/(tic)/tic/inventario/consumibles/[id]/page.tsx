import type { Metadata } from 'next'
import { ConsumibleView } from './ConsumibleView'

export const metadata: Metadata = {
  title: 'Consumible',
  description: 'Un consumible: los modelos a los que sirve y su kárdex',
}

interface Props {
  params: Promise<{ id: string }>
}

export default async function ConsumiblePage({ params }: Props) {
  const { id } = await params

  return <ConsumibleView id={Number(id)} />
}
