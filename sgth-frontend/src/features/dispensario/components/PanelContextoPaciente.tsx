'use client'

import {
  ActionIcon, Avatar, Card, Collapse, Group, Skeleton, Stack, Text, Textarea,
} from '@mantine/core'
import { IconChevronDown, IconChevronUp, IconUser, IconUsers } from '@tabler/icons-react'
import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import { DataState, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { AgregarAlergiaModal } from './AgregarAlergiaModal'
import { AgregarAntecedenteModal } from './AgregarAntecedenteModal'
import { AnularRegistroModal } from './AnularRegistroModal'
import { AvisoAlergiaGrave, SeccionAlergias } from './SeccionAlergias'
import { BloqueTriaje } from './BloqueTriaje'
import { SeccionAntecedentes } from './SeccionAntecedentes'
import { useContextoConsulta } from '../hooks/useContextoConsulta'
import { useAnularAlergia, useAnularAntecedente } from '../hooks/useHistoriaClinica'
import type { AgendaMedica } from '../services/agendaService'
import type { Triaje } from '../services/triajeService'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
}

/** Lo que se está anulando, mientras el modal pide el motivo. */
type RegistroAAnular = {
  id:   number
  tipo: 'alergia' | 'antecedente'
  desc: string
}

export function PanelContextoPaciente({ turno, historiaClinicaId }: Props) {
  const contained = useContainedInput()
  const { data: contexto, isLoading, error, refetch } = useContextoConsulta(
    historiaClinicaId, turno.id
  )
  const [notasAbiertas, setNotasAbiertas] = useState(false)

  const [alergiaOpened, alergiaModal]   = useDisclosure(false)
  const [personalOpened, personalModal] = useDisclosure(false)
  const [familiarOpened, familiarModal] = useDisclosure(false)
  const [anularOpened, anularModal]     = useDisclosure(false)

  const anularAlergia     = useAnularAlergia(historiaClinicaId, turno.id)
  const anularAntecedente = useAnularAntecedente(historiaClinicaId, turno.id)
  const [registroAnular, setRegistroAnular] = useState<RegistroAAnular | null>(null)

  const pedirAnulacion = (registro: RegistroAAnular) => {
    setRegistroAnular(registro)
    anularModal.open()
  }

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  if (isLoading) {
    return (
      <Card withBorder radius="lg" p="md" h="100%">
        <Skeleton height={300} radius="md" />
      </Card>
    )
  }

  // Sin esto, un fallo dejaba `contexto` en `undefined` y el panel se pintaba
  // entero y vacío: «Ninguna alergia registrada», «Ningún antecedente». Sobre
  // un paciente alérgico, delante del médico, es la peor frase de la pantalla.
  if (error) {
    return (
      <Card withBorder radius="lg" p="md" h="100%">
        <DataState
          loading={false}
          error={error}
          errorTitle="No se pudo cargar el contexto del paciente"
          errorHint="No quiere decir que no tenga alergias ni antecedentes: no se pudieron consultar."
          onRetry={() => refetch()}
        />
      </Card>
    )
  }

  const triaje   = contexto?.triaje_actual as Triaje | null | undefined
  const alergias = contexto?.historia_clinica.alergias ?? []
  const antecedentes = contexto?.historia_clinica.antecedentes ?? []
  const personales = antecedentes.filter(a => a.tipo !== 'familiar')
  const familiares = antecedentes.filter(a => a.tipo === 'familiar')

  return (
    <Card withBorder radius="lg" p="md" h="100%">
      <Stack gap="sm">
        <Group gap="xs" wrap="nowrap">
          <Avatar radius="xl" size="sm">
            {esServidor ? <IconUser size={14} /> : <IconUsers size={14} />}
          </Avatar>
          <Stack gap={0}>
            <Text size="sm" fw={600} lineClamp={1}>
              {nombrePaciente.trim() || '—'}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {turno.folio}
            </Text>
          </Stack>
        </Group>

        <AvisoAlergiaGrave alergias={alergias} />

        <BloqueTriaje triaje={triaje} />

        <SeccionAlergias
          alergias={alergias}
          onAgregar={alergiaModal.open}
          onAnular={(a) => pedirAnulacion({
            id: a.id, tipo: 'alergia', desc: a.descripcion,
          })}
        />

        <SeccionAntecedentes
          titulo="Antecedentes personales"
          antecedentes={personales}
          conTipo
          vacio="Ninguno registrado"
          onAgregar={personalModal.open}
          onAnular={(a) => pedirAnulacion({
            id: a.id, tipo: 'antecedente', desc: a.descripcion,
          })}
        />

        <SeccionAntecedentes
          titulo="Antecedentes familiares"
          antecedentes={familiares}
          vacio="Ninguno registrado"
          onAgregar={familiarModal.open}
          onAnular={(a) => pedirAnulacion({
            id: a.id, tipo: 'antecedente', desc: a.descripcion,
          })}
        />

        {/* El desplegable era un `div` con `cursor: pointer` y un `onClick`:
            con el ratón funcionaba y con el teclado no existía, porque nada
            en la página podía recibir el foco para abrirlo. */}
        <SectionHeading
          title="Notas del médico"
          action={
            <ActionIcon
              size="xs"
              variant="subtle"
              aria-expanded={notasAbiertas}
              aria-label={notasAbiertas
                ? 'Ocultar notas del médico'
                : 'Mostrar notas del médico'}
              onClick={() => setNotasAbiertas(v => !v)}
            >
              {notasAbiertas
                ? <IconChevronUp size={10} />
                : <IconChevronDown size={10} />}
            </ActionIcon>
          }
        />
        <Collapse expanded={notasAbiertas}>
          <Textarea
            placeholder="Notas adicionales durante la consulta..."
            autosize
            minRows={2}
            maxRows={4}
            {...contained}
          />
        </Collapse>
      </Stack>

      <AnularRegistroModal
        opened={anularOpened}
        onClose={() => { setRegistroAnular(null); anularModal.close() }}
        titulo={registroAnular?.tipo === 'alergia'
          ? 'Anular alergia'
          : 'Anular antecedente'}
        descripcion={`${registroAnular?.desc ?? ''}`}
        loading={anularAlergia.isPending || anularAntecedente.isPending}
        onConfirmar={(motivo) => {
          if (!registroAnular) return
          const mutate = registroAnular.tipo === 'alergia'
            ? anularAlergia
            : anularAntecedente
          mutate.mutate(
            { id: registroAnular.id, motivo },
            { onSuccess: () => {
              setRegistroAnular(null)
              anularModal.close()
            }}
          )
        }}
      />

      <AgregarAlergiaModal
        opened={alergiaOpened}
        onClose={alergiaModal.close}
        historiaId={historiaClinicaId}
        agendaId={turno.id}
      />
      <AgregarAntecedenteModal
        opened={personalOpened}
        onClose={personalModal.close}
        historiaId={historiaClinicaId}
        agendaId={turno.id}
        tipo="personal"
      />
      <AgregarAntecedenteModal
        opened={familiarOpened}
        onClose={familiarModal.close}
        historiaId={historiaClinicaId}
        agendaId={turno.id}
        tipo="familiar"
      />
    </Card>
  )
}
