'use client'

import Link from 'next/link'
import { Anchor, Button, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconClipboardCheck, IconHeartbeat } from '@tabler/icons-react'
import { DataState, SectionCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useMiJornada } from '../hooks/useMiJornada'
import { useTriajesPendientes } from '../hooks/useTriaje'
import { usePuedeTriar } from '../hooks/usePuedeTriar'
import { tiempoDeEspera } from '../constants/turnos'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onTomarTriaje: (turno: AgendaMedica) => void
}

const nombreDe = (t: AgendaMedica) =>
  (t.servidor_id
    ? `${t.servidor?.nombre ?? ''} ${t.servidor?.apellido ?? ''}`
    : `${t.carga_familiar?.nombres ?? ''} ${t.carga_familiar?.apellidos ?? ''}`).trim() || '—'

function Cifra({ valor, etiqueta }: { valor: number; etiqueta: string }) {
  return (
    <Stack gap={0}>
      <Text fw={700} fz="xl" lh={1.2}>{valor}</Text>
      <Text size="xs" c="dimmed">{etiqueta}</Text>
    </Stack>
  )
}

/**
 * Lo que espera a Enfermería hoy, al lado del buscador.
 *
 * Antes «Mi jornada» iba en tres tarjetas a todo el ancho, encima del
 * formulario —casi media pantalla de una laptop antes del campo de cédula—, y
 * los pendientes de triaje vivían en otra pantalla. Aquí están las cifras en
 * una línea y la fila de quienes esperan sus signos, del primero en llegar al
 * último, cada uno con su botón: atender y triar dejan de ser dos pantallas.
 */
export function HoyEnEnfermeria({ onTomarTriaje }: Props) {
  const puedeTriar = usePuedeTriar()
  const { data: jornada } = useMiJornada('enfermeria')
  const pendientes = useTriajesPendientes()
  const turnos = pendientes.data ?? []
  const deEnfermeria = jornada?.perfil === 'enfermeria' ? jornada : null

  return (
    <SectionCard
      title="Hoy en Enfermería"
      actions={
        <Anchor size="sm" component={Link} href={ROUTES.SALUD.ENFERMERIA_COLA}>
          Ver la cola completa
        </Anchor>
      }
    >
      <Stack gap="md">
        {deEnfermeria && (
          <SimpleGrid cols={3} spacing="xs">
            <Cifra valor={deEnfermeria.hoy.por_triar} etiqueta="por triar" />
            <Cifra valor={deEnfermeria.hoy.triaje_sso} etiqueta="salud ocupacional" />
            <Cifra valor={deEnfermeria.hoy.mis_atenciones} etiqueta="mis servicios" />
          </SimpleGrid>
        )}
        {deEnfermeria && deEnfermeria.hoy.triaje_sso > 0 && (
          <Anchor size="xs" component={Link} href={ROUTES.SALUD.ENFERMERIA_SSO}>
            {deEnfermeria.hoy.triaje_sso === 1
              ? '1 evaluación ocupacional espera sus signos vitales'
              : `${deEnfermeria.hoy.triaje_sso} evaluaciones ocupacionales esperan sus signos vitales`}
          </Anchor>
        )}

        <Text size="sm" fw={600}>Esperan su triaje</Text>
        <DataState
          loading={pendientes.isLoading}
          error={pendientes.error}
          errorTitle="No se pudieron cargar los pendientes"
          errorHint="Esto no significa que nadie esté esperando."
          onRetry={() => pendientes.refetch()}
          empty={!turnos.length}
          emptyProps={{ icon: IconHeartbeat, title: 'Nadie espera triaje', description: 'Los turnos de hoy ya tienen sus signos vitales.' }}
          skeletonRows={3}
        >
          <Stack gap="xs">
            {turnos.map((t) => (
              <Group key={t.id} justify="space-between" wrap="nowrap" gap="sm">
                <Stack gap={0} miw={0}>
                  <Text size="sm" fw={500} truncate>{nombreDe(t)}</Text>
                  <Text size="xs" c="dimmed">
                    <Text span ff="monospace" inherit>{t.folio}</Text>
                    {t.registrado_en && ` · espera ${tiempoDeEspera(pendientes.dataUpdatedAt - new Date(t.registrado_en).getTime())}`}
                  </Text>
                </Stack>
                {puedeTriar && (
                  <Button
                    size="xs"
                    variant="light"
                    leftSection={<IconClipboardCheck size={14} />}
                    onClick={() => onTomarTriaje(t)}
                  >
                    Tomar triaje
                  </Button>
                )}
              </Group>
            ))}
          </Stack>
        </DataState>
      </Stack>
    </SectionCard>
  )
}
