'use client'

import { use } from 'react'
import { useRouter } from 'next/navigation'
import { Stack, Skeleton, Alert, Text } from '@mantine/core'
import { IconAlertCircle } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { agendaService } from
  '@/features/dispensario/services/agendaService'
import { AtencionMedicaPanel } from
  '@/features/dispensario/components/AtencionMedicaPanel'
import { useTurnosDelDia } from
  '@/features/dispensario/hooks/useAgenda'

interface Props {
  params: Promise<{ folio: string }>
}

export default function ConsultaDetallePage({ params }: Props) {
  const { folio } = use(params)
  const router    = useRouter()

  const { data: turnos = [] } = useTurnosDelDia()
  const totalEnEspera = turnos.filter(t =>
    ['en_espera', 'en_sala'].includes(t.estado)
  ).length

  const {
    data: turno,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ['agenda', 'por-folio', folio],
    queryFn:  () => agendaService.obtenerPorFolio(folio),
    retry:    false,
    staleTime: 1000 * 30,
  })

  if (isLoading) {
    return (
      <Stack gap="md">
        <Skeleton height={60} radius="lg" />
        <Skeleton height={400} radius="lg" />
      </Stack>
    )
  }

  if (isError || !turno) {
    return (
      <Alert
        icon={<IconAlertCircle size={16} />}
        color="red"
        variant="light"
        title="Turno no encontrado"
      >
        <Text size="sm">
          El turno {folio} no existe o no está asignado a tu cuenta.
        </Text>
      </Alert>
    )
  }

  return (
    <AtencionMedicaPanel
      turno={turno}
      historiaClinicaId={turno.historia_clinica_id ?? 0}
      totalEnEspera={totalEnEspera}
      onFinalizar={() => router.push('/salud/consultas')}
    />
  )
}
