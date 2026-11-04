'use client'

import { useState } from 'react'
import { ActionIcon, Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconEdit, IconPlus, IconTrash } from '@tabler/icons-react'
import { confirmar, DataState, SectionHeading, StatusBadge } from '@/components/ui'
import type { PuestoCompetencia, TipoCompetencia } from '@/types/api'
import {
  ESPACIOS_POR_TIPO_EN_LA_ASIGNACION, complejidadEnFemenino, nivelSugerido, TIPOS_COMPETENCIA,
} from '../constants/perfilPuesto'
import { usePuestoCompetenciaMutations, usePuestoCompetencias } from '../hooks/useCompetencias'
import { PuestoCompetenciaModal } from './PuestoCompetenciaModal'

interface Props {
  puestoId:    number
  /** La complejidad del puesto (bajo, medio o alto): el nivel que se propone. */
  complejidad: string | null | undefined
  soloLectura: boolean
}

type Edicion = { tipo: TipoCompetencia; asignada: PuestoCompetencia | null }

/**
 * Las competencias técnicas y conductuales que exige el puesto, tomadas del
 * diccionario del Ministerio, con su nivel. Son las que el jefe copia en el
 * FOR-02 de quien ocupa el puesto.
 */
export function PuestoCompetenciasPanel({ puestoId, complejidad, soloLectura }: Props) {
  const { data: competencias = [], isLoading, error, refetch } = usePuestoCompetencias(puestoId)
  const { quitar } = usePuestoCompetenciaMutations(puestoId)
  const [edicion, setEdicion] = useState<Edicion | null>(null)

  // Con el error a la vista: una consulta fallida decía «El puesto todavía
  // no exige ninguna».
  return (
    <DataState
      loading={isLoading}
      error={error}
      errorTitle="No se pudieron cargar las competencias del puesto"
      onRetry={refetch}
      empty={false}
      skeletonRows={2}
    >
      <Stack gap="lg">
        <Text size="xs" c="dimmed">
          Al agregar una, se propone el nivel de la complejidad del puesto
          {complejidad ? ` (${complejidadEnFemenino(complejidad)})` : ''}; se puede cambiar.
        </Text>

        {TIPOS_COMPETENCIA.map(({ value: tipo, plural }) => {
          const delTipo = competencias.filter(c => c.tipo === tipo)
          return (
            <Stack key={tipo} gap="xs">
              <SectionHeading
                title={plural}
                action={!soloLectura && (
                  <Button
                    size="xs"
                    variant="light"
                    leftSection={<IconPlus size={14} />}
                    onClick={() => setEdicion({ tipo, asignada: null })}
                  >
                    Agregar
                  </Button>
                )}
              />
              {/* Sin ninguna lo dice la tarjeta de abajo; el aviso sobra. */}
              {delTipo.length > 0 && delTipo.length !== ESPACIOS_POR_TIPO_EN_LA_ASIGNACION && (
                <Text size="xs" c="dimmed">
                  La asignación de responsabilidades tiene espacio para {ESPACIOS_POR_TIPO_EN_LA_ASIGNACION} de
                  este tipo; el puesto exige {delTipo.length}.
                </Text>
              )}
              {delTipo.length === 0 ? (
                <Card withBorder radius="md" p="md">
                  <Text size="sm" c="dimmed" ta="center">El puesto todavía no exige ninguna.</Text>
                </Card>
              ) : delTipo.map(c => (
                <Card key={c.id} withBorder radius="md" p="sm">
                  <Group justify="space-between" wrap="nowrap" align="flex-start">
                    <Stack gap={4} style={{ flex: 1, minWidth: 0 }}>
                      <Group gap={6}>
                        <Text size="sm" fw={500}>{c.nombre}</Text>
                        <StatusBadge>Nivel {c.nivel_label.toLowerCase()}</StatusBadge>
                        <StatusBadge variant="outline">Comportamiento n.º {c.numero_comportamiento}</StatusBadge>
                        {!c.competencia_activa && <StatusBadge tone="warning">Retirada del diccionario</StatusBadge>}
                      </Group>
                      <Text size="xs" c="dimmed">{c.comportamiento}</Text>
                    </Stack>
                    {!soloLectura && (
                      <Group gap="xs" wrap="nowrap">
                        <ActionIcon
                          size="sm"
                          variant="subtle"
                          aria-label="Cambiar el nivel"
                          onClick={() => setEdicion({ tipo, asignada: c })}
                        >
                          <IconEdit size={13} />
                        </ActionIcon>
                        <ActionIcon
                          size="sm"
                          color="red"
                          variant="subtle"
                          aria-label="Quitar competencia"
                          onClick={() => confirmar({
                            title:   'Quitar competencia',
                            message: <>El puesto dejará de exigir <b>{c.nombre}</b>. Sigue en el diccionario.</>,
                            destructiva: true,
                            onConfirm: () => quitar.mutate(c.id),
                          })}
                        >
                          <IconTrash size={13} />
                        </ActionIcon>
                      </Group>
                    )}
                  </Group>
                </Card>
              ))}
            </Stack>
          )
        })}

        {!soloLectura && edicion && (
          <PuestoCompetenciaModal
            opened
            onClose={() => setEdicion(null)}
            puestoId={puestoId}
            tipo={edicion.tipo}
            nivelInicial={nivelSugerido(complejidad)}
            yaAsignadas={competencias.map(c => c.competencia_id)}
            asignada={edicion.asignada}
          />
        )}
      </Stack>
    </DataState>
  )
}
