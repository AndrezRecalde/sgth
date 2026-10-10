'use client'

import { Button, Group, Paper, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconNote } from '@tabler/icons-react'
import { MotivoModal, SectionHeading } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'

/**
 * Lo que se le anotó a la acción después de emitida (fase 1.4): la impugnación
 * del visto bueno que originó una cesación, una nota de Talento Humano. Un acto
 * registrado no se reescribe, así que esto va aparte y no sale en el documento.
 *
 * Sin anotaciones y sin permiso para anotar, no ocupa sitio.
 */
export function AccionAnotaciones({ m }: { m: MovimientoPersonal }) {
  const { anotar } = useMovimientoMutations()
  const [opened, { open, close }] = useDisclosure(false)

  const anotaciones = m.anotaciones ?? []
  if (anotaciones.length === 0 && !m.puede_anotar) return null

  return (
    <div>
      <SectionHeading
        title="Anotaciones"
        mb="xs"
        action={m.puede_anotar && (
          <Button size="xs" variant="subtle" leftSection={<IconNote size={14} />} onClick={open}>
            Anotar
          </Button>
        )}
      />

      {anotaciones.length === 0 ? (
        <Text size="sm" c="dimmed">
          Sin anotaciones. Lo que haya que dejar dicho después de emitida la acción va aquí,
          sin cambiar el documento.
        </Text>
      ) : (
        <Stack gap="xs">
          {anotaciones.map((a) => (
            <Paper key={a.id} withBorder p="xs" radius="sm">
              <Group justify="space-between" wrap="nowrap" align="baseline">
                <Text size="sm" fw={500}>{a.etiqueta}</Text>
                <Text size="xs" c="dimmed" style={{ whiteSpace: 'nowrap' }}>
                  {formatFechaHora(a.created_at)}
                  {a.registrado_por ? ` · ${a.registrado_por}` : ''}
                </Text>
              </Group>
              <Text size="sm" mt={2}>{a.texto}</Text>
            </Paper>
          ))}
        </Stack>
      )}

      <MotivoModal
        opened={opened}
        onClose={close}
        title="Anotar en la acción de personal"
        descripcion={
          <>
            La anotación queda junto a la acción{m.codigo_registro ? ` ${m.codigo_registro}` : ''},
            con su fecha y su autor. No cambia el documento ni se puede editar después. Si el
            acto tiene un error, lo que corresponde es anularlo y emitir uno nuevo.
          </>
        }
        etiqueta="Anotación"
        placeholder="Qué hay que dejar dicho sobre esta acción"
        confirmLabel="Anotar"
        cargando={anotar.isPending}
        onConfirm={(texto) => anotar.mutate({ id: Number(m.id), texto }, { onSuccess: close })}
      />
    </div>
  )
}
