'use client'

import { ActionIcon, Group, Stack, Text } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { SectionHeading } from '@/components/ui'
import type { AntecedentePaciente } from '../services/historiaClinicaService'

interface Props {
  titulo: string
  antecedentes: AntecedentePaciente[]
  /**
   * Los personales anteponen su tipo («quirúrgico: …»); los familiares no,
   * porque su tipo es siempre «familiar» y repetirlo no dice nada.
   */
  conTipo?: boolean
  /** Lo que dice la lista cuando está vacía. */
  vacio: string
  onAgregar: () => void
  onAnular:  (antecedente: AntecedentePaciente) => void
}

/**
 * Una lista de antecedentes del panel de contexto.
 *
 * Personales y familiares se pintaban con dos bloques casi calcados, cuya
 * única diferencia era ese `conTipo`.
 */
export function SeccionAntecedentes({
  titulo, antecedentes, conTipo = false, vacio, onAgregar, onAnular,
}: Props) {
  return (
    <>
      <SectionHeading
        title={titulo}
        action={
          <ActionIcon
            size="xs"
            variant="subtle"
            aria-label={`Agregar ${titulo.toLowerCase()}`}
            onClick={onAgregar}
          >
            <IconPlus size={10} />
          </ActionIcon>
        }
      />
      {antecedentes.length === 0 ? (
        <Text size="xs" c="dimmed">{vacio}</Text>
      ) : (
        <Stack gap={3}>
          {antecedentes.map((a) => (
            <Group key={a.id} justify="space-between" wrap="nowrap" align="flex-start">
              <Text size="xs" style={{ flex: 1 }}>
                {conTipo && (
                  <Text span fw={500} c="dimmed">{a.tipo}: </Text>
                )}
                {a.descripcion}
              </Text>
              <ActionIcon
                size="xs"
                variant="subtle"
                aria-label={`Anular antecedente: ${a.descripcion}`}
                onClick={() => onAnular(a)}
              >
                <IconTrash size={10} />
              </ActionIcon>
            </Group>
          ))}
        </Stack>
      )}
    </>
  )
}
