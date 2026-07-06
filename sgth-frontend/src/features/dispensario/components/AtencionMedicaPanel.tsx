'use client'

import {
  Grid, Card, Tabs, Group, Text, Badge,
  Button, Stack, Avatar, ScrollArea,
} from '@mantine/core'
import {
  IconStethoscope, IconPill,
  IconCertificate, IconHistory,
  IconUser, IconUsers, IconArrowRight,
} from '@tabler/icons-react'
import { useState, useEffect } from 'react'
import { useAccionesTurno } from '../hooks/useAgenda'
import { useConsultaMedicaDetalle } from '../hooks/useConsultaMedica'
import { PanelContextoPaciente } from './PanelContextoPaciente'
import { TabConsulta } from './TabConsulta'
import { TabReceta } from './TabReceta'
import { TabHistorial } from './TabHistorial'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
  totalEnEspera:     number
  onFinalizar:       () => void
}

const ESTADO_COLOR: Record<string, string> = {
  en_espera:     'gray',
  en_sala:       'blue',
  en_consulta:   'blue',
  atendido:      'emerald',
  no_presentado: 'orange',
}

export function AtencionMedicaPanel({
  turno, historiaClinicaId,
  totalEnEspera, onFinalizar,
}: Props) {
  const consultaExistenteId = turno.consulta_medica?.id ?? null
  const { data: consultaCargada } = useConsultaMedicaDetalle(
    consultaExistenteId
  )

  const [consultaGuardada, setConsultaGuardada] =
    useState<ConsultaMedica | null>(null)
  const [activeTab, setActiveTab] = useState<string>('consulta')
  const { enConsulta } = useAccionesTurno()

  const consultaActiva = consultaGuardada ?? consultaCargada ?? null

  useEffect(() => {
    if (consultaCargada && !consultaGuardada) {
      setActiveTab('consulta')
    }
  }, [consultaCargada, consultaGuardada])

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  const estadoColor = ESTADO_COLOR[turno.estado] ?? 'gray'

  const handleGuardada = (consulta: ConsultaMedica) => {
    setConsultaGuardada(consulta)
    setActiveTab('receta')
  }

  const handleIniciarConsulta = () => {
    if (turno.estado === 'en_espera' || turno.estado === 'en_sala') {
      enConsulta.mutate(turno.id)
    }
  }

  return (
    <>
      <Stack gap="sm">
        <Card withBorder radius="lg" p="sm">
          <Group justify="space-between" wrap="nowrap">
            <Group gap="sm" wrap="nowrap">
              <Avatar
                color={esServidor ? 'emerald' : 'blue'}
                radius="xl"
                size="md"
              >
                {esServidor
                  ? <IconUser size={16} />
                  : <IconUsers size={16} />}
              </Avatar>
              <Stack gap={0}>
                <Group gap="xs">
                  <Text size="sm" fw={700}>
                    {nombrePaciente.trim() || '—'}
                  </Text>
                  <Badge
                    size="xs"
                    variant="light"
                    color={estadoColor}
                  >
                    {turno.estado.replace('_', ' ')}
                  </Badge>
                </Group>
                <Text size="xs" c="dimmed" ff="monospace">
                  {turno.folio} · {esServidor ? 'Servidor' : 'Familiar'}
                </Text>
              </Stack>
            </Group>

            <Group gap="xs" wrap="nowrap">
              {totalEnEspera > 0 && (
                <Badge size="sm" variant="light" color="gray">
                  {totalEnEspera} en espera
                </Badge>
              )}
              <Button
                size="xs"
                variant="light"
                color="emerald"
                rightSection={<IconArrowRight size={13} />}
                onClick={onFinalizar}
              >
                Finalizar
              </Button>
            </Group>
          </Group>
        </Card>

        <Grid>
          <Grid.Col span={{ base: 12, md: 4, lg: 3 }}>
            <ScrollArea
              h={{ base: 'auto', md: 'calc(100vh - 200px)' }}
              type="auto"
            >
              <PanelContextoPaciente
                turno={turno}
                historiaClinicaId={historiaClinicaId}
              />
            </ScrollArea>
          </Grid.Col>

          <Grid.Col span={{ base: 12, md: 8, lg: 9 }}>
            <Card withBorder radius="lg" p={0}>
              <Tabs
                value={activeTab}
                onChange={(v) => setActiveTab(v ?? 'consulta')}
              >
                <Tabs.List px="sm">
                  <Tabs.Tab
                    value="consulta"
                    leftSection={<IconStethoscope size={13} />}
                  >
                    Consulta
                  </Tabs.Tab>
                  <Tabs.Tab
                    value="receta"
                    leftSection={<IconPill size={13} />}
                    disabled={!consultaActiva}
                  >
                    Receta
                  </Tabs.Tab>
                  <Tabs.Tab
                    value="historial"
                    leftSection={<IconHistory size={13} />}
                  >
                    Historial
                  </Tabs.Tab>
                  <Tabs.Tab
                    value="certificado"
                    leftSection={<IconCertificate size={13} />}
                    disabled={!consultaActiva}
                  >
                    Certificado
                  </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="consulta">
                  <TabConsulta
                    turno={turno}
                    historiaClinicaId={historiaClinicaId}
                    consultaPrevia={consultaActiva}
                    onGuardada={handleGuardada}
                  />
                </Tabs.Panel>

                <Tabs.Panel value="receta">
                  {consultaActiva ? (
                    <TabReceta
                      turno={turno}
                      consulta={consultaActiva}
                    />
                  ) : (
                    <Stack gap="sm" p="md">
                      <Text size="sm" c="dimmed">
                        Guarda la consulta primero para
                        poder emitir recetas.
                      </Text>
                    </Stack>
                  )}
                </Tabs.Panel>

                <Tabs.Panel value="historial">
                  <TabHistorial
                    historiaClinicaId={historiaClinicaId}
                  />
                </Tabs.Panel>

                <Tabs.Panel value="certificado">
                  <Stack gap="sm" p="md">
                    <Text size="sm" c="dimmed">
                      Emite un certificado médico para esta consulta.
                    </Text>
                  </Stack>
                </Tabs.Panel>
              </Tabs>
            </Card>
          </Grid.Col>
        </Grid>
      </Stack>

    </>
  )
}
