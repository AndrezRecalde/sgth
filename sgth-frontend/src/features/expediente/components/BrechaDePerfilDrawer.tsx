'use client'

import { Button, List, Stack, Text } from '@mantine/core'
import { IconFolder } from '@tabler/icons-react'
import { DetailList, SectionHeading, SgthDrawer, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { estadoInstruccion } from '../constants/brechasDePerfil'
import type { BrechaDePerfil } from '../services/brechasDePerfilService'

interface Props {
  brecha:  BrechaDePerfil | null
  onClose: () => void
  alVerExpediente: (b: BrechaDePerfil) => void
}

/** Lo que el servidor tiene frente a lo que pide el perfil de su puesto, entero (P7, PR 17). */
export function BrechaDePerfilDrawer({ brecha, onClose, alVerExpediente }: Props) {
  return (
    <SgthDrawer opened={brecha !== null} onClose={onClose} title="Brecha de perfil" description={brecha?.servidor.nombre}>
      {brecha && <Contenido brecha={brecha} alVerExpediente={alVerExpediente} />}
    </SgthDrawer>
  )
}

function Contenido({ brecha, alVerExpediente }: { brecha: BrechaDePerfil; alVerExpediente: (b: BrechaDePerfil) => void }) {
  const { puesto, instruccion, capacitacion } = brecha
  const { label, tone } = estadoInstruccion(instruccion.estado)

  return (
    <Stack gap="lg">
      <DetailList items={[
        { label: 'Puesto', value: puesto.cargo ?? `Puesto ${puesto.id}` },
        { label: 'Unidad', value: [puesto.unidad, puesto.grado ? `grado ${puesto.grado}` : null].filter(Boolean).join(' · ') || '—' },
      ]} />

      <Stack gap="xs">
        <SectionHeading title="Instrucción formal" action={<StatusBadge tone={tone}>{label}</StatusBadge>} />
        {instruccion.estado === 'sin_requisitos' ? (
          <Text size="sm" c="dimmed">
            El perfil del puesto no tiene alternativas de instrucción: no hay con qué comparar. Se completan en Puestos y cargos.
          </Text>
        ) : (
          <DetailList columnas={1} items={[
            { label: 'Título validado más alto', value: instruccion.tiene ?? 'Ninguno' },
            { label: 'El puesto pide, una de estas', value: instruccion.pide.join(' o ') },
          ]} />
        )}
        {instruccion.estado === 'por_revisar' && (
          <Text size="xs" c="dimmed">
            El puesto acepta estudios superiores en curso, que no se registran como título: revíselo con el servidor.
          </Text>
        )}
        {instruccion.por_validar > 0 && (
          <Text size="xs" c="amber">
            {instruccion.por_validar === 1 ? 'Tiene un título por validar' : `Tiene ${instruccion.por_validar} títulos por validar`} en
            «Títulos por validar»: no cuentan hasta que se validen.
          </Text>
        )}
        <Text size="xs" c="dimmed">El área del título y la experiencia no se comparan.</Text>
      </Stack>

      <Stack gap="xs">
        <SectionHeading title="Capacitación" />
        <div>
          <Text size="xs" c="dimmed">Temáticas que pide el puesto</Text>
          <Text size="sm" c={capacitacion.tematicas ? undefined : 'dimmed'} style={{ whiteSpace: 'pre-line' }}>
            {capacitacion.tematicas ?? 'El perfil no pide temáticas.'}
          </Text>
        </div>
        {capacitacion.tematicas && (
          <div>
            <Text size="xs" c="dimmed">
              Cursos validados que tratan de esas temáticas ({capacitacion.relacionadas.length} de {capacitacion.registradas})
            </Text>
            {capacitacion.relacionadas.length === 0 ? (
              <Text size="sm" c="dimmed">Ninguno.</Text>
            ) : (
              <List size="sm" spacing={2}>
                {capacitacion.relacionadas.map((c, i) => (
                  <List.Item key={i}>{c.titulo}{c.fecha ? ` (${formatFecha(c.fecha)})` : ''}</List.Item>
                ))}
              </List>
            )}
            <Text size="xs" c="dimmed" mt={4}>
              Se señalan los que comparten palabras con las temáticas: es una pista para revisar, no un «cumple».
            </Text>
          </div>
        )}
      </Stack>

      <Button variant="light" leftSection={<IconFolder size={14} />} onClick={() => alVerExpediente(brecha)} style={{ alignSelf: 'flex-start' }}>
        Abrir el expediente
      </Button>
    </Stack>
  )
}
