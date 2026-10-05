'use client'

import { Alert, Box, Button, Card, Group, Skeleton, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconSend, IconTrophy } from '@tabler/icons-react'
import { useMemo, useState } from 'react'
import { confirmar, StatusBadge } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useConfirmarIncorporacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { usePostulantes, useEnviarAlDispensario } from '../hooks/useConvocatoria'
import { DeclararSiguienteAviso } from './DeclararSiguienteAviso'
import { RankingCandidatoCard, nombreCandidato } from './RankingCandidatoCard'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  convocatoriaId:      number
  estadoConvocatoria:  string
  /** Tope de ganadores declarables. El backend es la autoridad. */
  vacantes?:           number
}

export function TabRanking({ convocatoriaId, estadoConvocatoria, vacantes = 1 }: Props) {
  const { data: postulantes = [], isLoading } = usePostulantes(convocatoriaId)
  const enviar = useEnviarAlDispensario(convocatoriaId)
  const incorporar = useConfirmarIncorporacion()
  const { hasPermiso } = useAuth()
  const puedeIncorporar = hasPermiso('gestionar-onboarding')

  const [seleccionados, setSeleccionados] = useState<number[]>([])
  const alternar = (id: number) =>
    setSeleccionados((previos) =>
      previos.includes(id) ? previos.filter((x) => x !== id) : [...previos, id],
    )

  const ranking = useMemo(
    () => [...postulantes]
      .filter((p) => p.evaluacion)
      .sort((a, b) => (b.evaluacion?.puntaje_total ?? 0) - (a.evaluacion?.puntaje_total ?? 0)),
    [postulantes],
  )

  const sinCalificar = postulantes.filter((p) => !p.evaluacion)
  const enEvalMedica = estadoConvocatoria === 'en_evaluacion_medica'
  const finalizada = estadoConvocatoria === 'finalizada'
  const puedeEnviar = !enEvalMedica && !finalizada
  // Todos los enviados, no solo el primero: con dos o más vacantes el aviso
  // nombraba a uno.
  const enviados = postulantes.filter((p) => p.estado === 'ganador_potencial')

  // La vacante que dejó un no apto, o una solicitud médica cancelada (el
  // candidato vuelve a la lista de espera), la cubre el primero de la lista en
  // el ranking. El backend lo elige igual; aquí solo se nombra.
  const ocupadas = postulantes.filter((p) => ['ganador_potencial', 'incorporado'].includes(p.estado)).length
  const siguiente = ranking.find((p) => p.estado === 'lista_espera')
  const puedeDeclararSiguiente = enEvalMedica && ocupadas < vacantes

  const confirmarIncorporacion = (p: Postulante) => {
    const solicitudId = p.solicitud_certificacion?.id
    if (!solicitudId) return
    confirmar({
      title: 'Confirmar incorporación',
      message: (
        <>
          Se creará el expediente de servidor de <b>{nombreCandidato(p)}</b> con su
          acción de ingreso en borrador. No se puede deshacer.
        </>
      ),
      confirmLabel: 'Incorporar',
      onConfirm: () => incorporar.mutate(solicitudId),
    })
  }

  const enviarAlDispensario = () => {
    const elegidos = ranking.filter((p) => seleccionados.includes(p.id))
    confirmar({
      title: 'Enviar al Dispensario Médico',
      message: (
        <>
          Se enviará al Dispensario Médico a <b>{elegidos.length} candidato(s)</b>:
          <Box component="ul" my="xs" pl="md">
            {elegidos.map((p) => <li key={p.id}>{nombreCandidato(p)}</li>)}
          </Box>
          La convocatoria queda en espera de los dictámenes médicos. Los demás
          aprobados pasan a lista de espera.
        </>
      ),
      confirmLabel: 'Enviar',
      onConfirm: () => enviar.mutate(seleccionados, { onSuccess: () => setSeleccionados([]) }),
    })
  }

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    )
  }

  return (
    <Stack gap="md" p="md">
      {finalizada && (
        <Alert color="emerald" variant="light" icon={<IconTrophy size={16} />}>
          <Text size="xs">
            Esta convocatoria fue finalizada: sus ganadores fueron incorporados.
          </Text>
        </Alert>
      )}

      {enEvalMedica && enviados.length > 0 && (
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            En evaluación médica: <strong>{enviados.map(nombreCandidato).join(', ')}</strong>.
            Cuando el Dispensario emita el dictamen de aptitud, Talento Humano
            confirma aquí la incorporación de cada uno. Al incorporar al último,
            la convocatoria se finaliza sola.
          </Text>
        </Alert>
      )}

      {puedeDeclararSiguiente && siguiente && (
        <DeclararSiguienteAviso convocatoriaId={convocatoriaId} siguiente={siguiente} />
      )}

      {!enEvalMedica && !finalizada && ranking.length === 0 && (
        <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Ningún candidato ha sido calificado aún. Califique a los candidatos
            para generar el ranking.
          </Text>
        </Alert>
      )}

      {sinCalificar.length > 0 && !finalizada && (
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            {sinCalificar.length === 1
              ? '1 candidato aún no ha sido calificado.'
              : `${sinCalificar.length} candidatos aún no han sido calificados.`}
          </Text>
        </Alert>
      )}

      {ranking.length > 0 && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase" style={{ letterSpacing: '0.05em' }}>
              Ranking de candidatos
            </Text>
            <StatusBadge>
              {ranking.length} calificado{ranking.length !== 1 ? 's' : ''}
            </StatusBadge>
          </Group>

          {puedeEnviar && seleccionados.length > 0 && (
            <Card withBorder radius="md" padding="sm" bg="var(--sgth-surface-sunken)">
              <Group justify="space-between" wrap="nowrap">
                <div>
                  <Text size="sm" fw={600}>
                    {seleccionados.length} de {vacantes} vacante{vacantes !== 1 ? 's' : ''}{' '}
                    seleccionada{seleccionados.length !== 1 ? 's' : ''}
                  </Text>
                  <Text size="xs" c="dimmed">
                    Cada candidato recibe su propia solicitud de certificación médica.
                  </Text>
                </div>
                <Button
                  size="xs"
                  leftSection={<IconSend size={13} />}
                  loading={enviar.isPending}
                  onClick={enviarAlDispensario}
                >
                  Enviar al Dispensario
                </Button>
              </Group>
            </Card>
          )}

          {ranking.map((p, i) => (
            <RankingCandidatoCard
              key={p.id}
              p={p}
              posicion={i + 1}
              seleccionable={puedeEnviar}
              seleccionado={seleccionados.includes(p.id)}
              sinCupo={seleccionados.length >= vacantes}
              onAlternar={alternar}
              puedeIncorporar={puedeIncorporar}
              incorporando={incorporar.isPending && incorporar.variables === p.solicitud_certificacion?.id}
              onIncorporar={confirmarIncorporacion}
            />
          ))}
        </Stack>
      )}
    </Stack>
  )
}
