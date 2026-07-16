'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Stack, Skeleton } from '@mantine/core'

// Toda ficha FEMO nace de una solicitud de Talento Humano; sin un
// identificador de solicitud en la ruta no hay nada válido que mostrar.
export default function NuevaFemoSinSolicitudPage() {
  const router = useRouter()

  useEffect(() => {
    router.replace('/salud/sso')
  }, [router])

  return (
    <Stack gap="md">
      <Skeleton height={60} radius="lg" />
      <Skeleton height={400} radius="lg" />
    </Stack>
  )
}
