'use client'

import { Button } from '@mantine/core'
import { IconFilePlus, IconFileOff } from '@tabler/icons-react'
import { useQueryClient } from '@tanstack/react-query'
import { EmptyState } from '@/components/ui'
import { useCrearHistoriaClinica } from '../hooks/useHistoriaClinica'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turno: AgendaMedica
}

/**
 * La ficha de un turno cuyo paciente no tiene historia clínica.
 *
 * La recepción la abre antes de dar el turno, pero un turno puede llegar sin
 * ella. Antes se pasaba un 0 como historia: el contexto no cargaba, las
 * alergias decían «Ninguna registrada» —que es afirmar algo que no se sabe—,
 * y guardar la consulta fallaba con un 422 al final del trabajo. Abrirla aquí
 * engancha también la que existiera a nombre de la cédula.
 */
export function SinHistoriaClinica({ turno }: Props) {
  const qc = useQueryClient()
  const crear = useCrearHistoriaClinica()

  const abrir = () => crear.mutate(
    turno.servidor_id
      ? { servidor_id: turno.servidor_id }
      : { carga_familiar_id: turno.carga_familiar_id },
    {
      onSuccess: () => qc.invalidateQueries({
        queryKey: ['agenda', 'por-folio'],
      }),
    },
  )

  return (
    <EmptyState
      icon={IconFileOff}
      title="El paciente no tiene historia clínica"
      description="Ábrala para poder registrar la consulta. Si ya tenía una a su cédula, se usará esa."
      action={(
        <Button
          leftSection={<IconFilePlus size={16} />}
          loading={crear.isPending}
          onClick={abrir}
        >
          Abrir historia clínica
        </Button>
      )}
    />
  )
}
