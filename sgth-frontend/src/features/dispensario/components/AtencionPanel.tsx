'use client'

import { Box, Card, Grid, Stack, Tabs, Text } from '@mantine/core'
import classes from './PanelContextoPaciente.module.css'
import {
  IconCertificate, IconDental, IconHistory, IconMicroscope, IconPill,
  IconStethoscope,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useConsultaMedicaDetalle } from '../hooks/useConsultaMedica'
import { CabeceraPacienteAtencion } from './CabeceraPacienteAtencion'
import { PanelContextoPaciente } from './PanelContextoPaciente'
import { TabConsulta } from './TabConsulta'
import { DataState } from '@/components/ui'
import { TabReceta } from './TabReceta'
import { TabCertificado } from './TabCertificado'
import { TabHistorial } from './TabHistorial'
import { TabResultados } from './TabResultados'
import { TabOdontograma } from './TabOdontograma'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
  totalEnEspera:     number
  onFinalizar:       () => void
}

/**
 * Las tres pestañas que dependen de la consulta enseñaban este mismo aviso,
 * escrito tres veces con la única diferencia de la última palabra.
 */
function RequiereConsulta({ para }: { para: string }) {
  return (
    <Stack gap="sm" p="md">
      <Text size="sm" c="dimmed">
        Guarde la consulta primero para poder {para}.
      </Text>
    </Stack>
  )
}

/**
 * La ficha de atención de un turno, de Medicina general o de Odontología.
 *
 * Eran dos paneles: el de Odontología era una copia de este, con su propia
 * cabecera y sus propios avisos, más la pestaña del odontograma. Las copias se
 * separaron: Odontología conservaba el efecto que devolvía a «Consulta» sin que
 * nadie la pulsara, que Medicina ya había quitado, y dejaba registrar
 * procedimientos antes de guardar la consulta. Ahora es uno solo, y el
 * odontograma lo decide el turno, no la dirección: a quien atiende las dos
 * especialidades, un turno de odontología abierto desde «Consultas» le salía
 * sin odontograma.
 */
export function AtencionPanel({
  turno, historiaClinicaId,
  totalEnEspera, onFinalizar,
}: Props) {
  const conOdontograma = turno.tipo_atencion === 'odontologia'

  const consultaExistenteId = turno.consulta_medica?.id ?? null
  const {
    data: consultaCargada, isLoading: cargandoConsulta,
    error: errorConsulta, refetch: reintentarConsulta,
  } = useConsultaMedicaDetalle(consultaExistenteId)

  const [consultaGuardada, setConsultaGuardada] =
    useState<ConsultaMedica | null>(null)
  const [activeTab, setActiveTab] = useState<string>('consulta')

  // Manda lo leído del servidor, que se refresca al corregir; la respuesta del
  // alta solo cubre el rato hasta que llega. Al revés, tras corregir en la
  // misma visita seguía mandando el alta: se enseñaba el texto anterior y la
  // siguiente corrección partía de él.
  const consultaActiva = consultaCargada ?? consultaGuardada ?? null

  // Antes había aquí un efecto que devolvía la pestaña a «Consulta» cuando
  // llegaba `consultaCargada`. Como es el resultado de React Query, cada
  // refresco traía una referencia nueva y el efecto se repetía: el médico que
  // estuviera en «Receta» o «Resultados» era devuelto a «Consulta» sin tocar
  // nada. La pestaña ya arranca en 'consulta' por el useState de arriba.

  const handleGuardada = (consulta: ConsultaMedica) => {
    setConsultaGuardada(consulta)
    setActiveTab('receta')
  }

  return (
    <>
      <Stack gap="sm">
        <CabeceraPacienteAtencion
          turno={turno}
          totalEnEspera={totalEnEspera}
          onFinalizar={onFinalizar}
        />

        <Grid>
          <Grid.Col span={{ base: 12, md: 4, lg: 3 }}>
            <Box className={classes.contexto}>
              <PanelContextoPaciente
                turno={turno}
                historiaClinicaId={historiaClinicaId}
              />
            </Box>
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
                  {conOdontograma && (
                    <Tabs.Tab
                      value="odontograma"
                      leftSection={<IconDental size={13} />}
                    >
                      Odontograma
                    </Tabs.Tab>
                  )}
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
                  <Tabs.Tab
                    value="resultados"
                    leftSection={<IconMicroscope size={13} />}
                    disabled={!consultaActiva}
                  >
                    Resultados
                  </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="consulta">
                  {/* Si el turno ya tiene consulta, el formulario espera a leerla. Antes
                      mientras cargaba —o si fallaba— se ofrecía el de alta, con «Guardar
                      consulta» activo, y se podía registrar una segunda sobre el mismo
                      turno. */}
                  <DataState
                    loading={cargandoConsulta}
                    error={consultaActiva ? undefined : errorConsulta}
                    errorTitle="No se pudo cargar la consulta de este turno"
                    errorHint="El turno ya tiene una consulta registrada: hasta leerla no se ofrece el formulario, para no registrarla dos veces."
                    onRetry={() => reintentarConsulta()}
                    skeletonRows={4}
                  >
                    <TabConsulta
                      turno={turno}
                      historiaClinicaId={historiaClinicaId}
                      consultaPrevia={consultaActiva}
                      onGuardada={handleGuardada}
                    />
                  </DataState>
                </Tabs.Panel>

                {conOdontograma && (
                  <Tabs.Panel value="odontograma">
                    <TabOdontograma
                      historiaClinicaId={historiaClinicaId}
                      consultaMedicaId={consultaActiva?.id ?? null}
                    />
                  </Tabs.Panel>
                )}

                <Tabs.Panel value="receta">
                  {consultaActiva ? (
                    <TabReceta
                      turno={turno}
                      consulta={consultaActiva}
                    />
                  ) : <RequiereConsulta para="emitir recetas" />}
                </Tabs.Panel>

                <Tabs.Panel value="historial">
                  <TabHistorial
                    historiaClinicaId={historiaClinicaId}
                  />
                </Tabs.Panel>

                <Tabs.Panel value="certificado">
                  {consultaActiva ? (
                    <TabCertificado
                      turno={turno}
                      consulta={consultaActiva}
                    />
                  ) : <RequiereConsulta para="emitir certificados" />}
                </Tabs.Panel>

                <Tabs.Panel value="resultados">
                  {consultaActiva ? (
                    <TabResultados
                      consulta={consultaActiva}
                      historiaClinicaId={historiaClinicaId}
                    />
                  ) : <RequiereConsulta para="subir resultados" />}
                </Tabs.Panel>
              </Tabs>
            </Card>
          </Grid.Col>
        </Grid>
      </Stack>

    </>
  )
}
