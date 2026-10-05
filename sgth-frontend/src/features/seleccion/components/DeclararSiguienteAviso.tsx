'use client'

import { Alert, Button, Group, Text } from '@mantine/core'
import { IconUserX } from '@tabler/icons-react'
import { confirmar } from '@/components/ui'
import { useDeclararSiguiente } from '../hooks/useDeclararSiguiente'
import { nombreCandidato } from './RankingCandidatoCard'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  convocatoriaId: number
  /** El primero de la lista de espera en el ranking. */
  siguiente:      Postulante
}

/**
 * La vacante que deja un no apto (2026-10-04) o una solicitud médica
 * cancelada (2026-10-05). Antes el concurso se quedaba
 * en evaluación médica para siempre; ahora Talento Humano envía al siguiente
 * del ranking. Si no queda nadie en lista de espera, el backend cierra el
 * concurso solo y este aviso no aparece.
 */
export function DeclararSiguienteAviso({ convocatoriaId, siguiente }: Props) {
  const declarar = useDeclararSiguiente(convocatoriaId)

  const confirmarEnvio = () =>
    confirmar({
      title: 'Declarar al siguiente del ranking',
      message: (
        <>
          Se enviará al Dispensario Médico a <b>{nombreCandidato(siguiente)}</b>,
          el primero de la lista de espera, para cubrir la vacante.
        </>
      ),
      confirmLabel: 'Enviar',
      onConfirm: () => declarar.mutate(),
    })

  return (
    <Alert color="amber" variant="light" icon={<IconUserX size={16} />}>
      <Group justify="space-between" gap="xs">
        <Text size="xs" style={{ flex: 1 }}>
          Queda una vacante sin cubrir: un ganador fue declarado no apto o se
          canceló su evaluación médica. El siguiente del ranking es <strong>{nombreCandidato(siguiente)}</strong>.
        </Text>
        <Button size="xs" loading={declarar.isPending} onClick={confirmarEnvio}>
          Declarar al siguiente
        </Button>
      </Group>
    </Alert>
  )
}
