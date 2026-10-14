'use client'

import { useState } from 'react'
import { Button, Group, Menu } from '@mantine/core'
import {
  IconArrowUpRight, IconBan, IconChevronDown, IconCircleCheck, IconDevices, IconHandGrab, IconMessageQuestion, IconUserShare,
} from '@tabler/icons-react'
import { FormModal, MotivoModal } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { BuscarEquipoSelect } from '@/features/inventario-ti/components/BuscarEquipoSelect'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import type { Ticket } from '../services/tipos'
import { AsignarTicketModal } from './AsignarTicketModal'
import { EscalarTicketModal } from './EscalarTicketModal'
import { ResolverTicketModal } from './ResolverTicketModal'

type Accion = 'asignar' | 'preguntar' | 'resolver' | 'escalar' | 'anular' | 'equipo' | null

/**
 * Los pasos de TIC sobre un ticket, según su estado y quién mira: el principal
 * como botón y los demás en un menú. Que el paso exista lo vuelve a comprobar
 * el backend.
 */
export function AccionesTicketTic({ ticket: t }: { ticket: Ticket }) {
  const { usuario, hasPermiso } = useAuth()
  const { tomar, pedirInformacion, anular, vincularBien } = useTicketTicMutations()
  const [accion, setAccion] = useState<Accion>(null)
  const [equipo, setEquipo] = useState<number | null>(t.bien?.id ?? null)

  const atiende = hasPermiso('gestionar-tickets')
  const reparte = hasPermiso('asignar-ticket')
  const esMio = t.tecnico_id === usuario?.id
  const enCurso = ['abierto', 'asignado', 'en_proceso', 'pendiente_usuario'].includes(t.estado)
  const cerrar = () => setAccion(null)

  const puedeTomar = atiende && ['abierto', 'asignado'].includes(t.estado) && (!t.tecnico_id || esMio || reparte)
  const trabajando = atiende && ['en_proceso', 'pendiente_usuario'].includes(t.estado) && (esMio || reparte)

  const secundarias = [
    { key: 'asignar', label: t.tecnico_id ? 'Reasignar' : 'Asignar', icon: IconUserShare, visible: reparte && enCurso && t.estado !== 'pendiente_usuario' },
    { key: 'preguntar', label: 'Pedir información', icon: IconMessageQuestion, visible: trabajando && t.estado === 'en_proceso' },
    { key: 'escalar', label: 'Escalar', icon: IconArrowUpRight, visible: atiende && ['asignado', 'en_proceso'].includes(t.estado) && t.nivel < 3 && (esMio || reparte) },
    { key: 'equipo', label: t.bien ? 'Cambiar el equipo' : 'Vincular un equipo', icon: IconDevices, visible: atiende && enCurso },
    { key: 'anular', label: 'Anular', icon: IconBan, visible: reparte && enCurso },
  ].filter((a) => a.visible)

  return (
    <>
      <Group gap="xs">
        {puedeTomar && <Button variant="light" leftSection={<IconHandGrab size={16} />} loading={tomar.isPending} onClick={() => tomar.mutate(t.id)}>Tomar</Button>}
        {trabajando && <Button variant="light" leftSection={<IconCircleCheck size={16} />} onClick={() => setAccion('resolver')}>Resolver</Button>}
        {secundarias.length > 0 && (
          <Menu position="bottom-end">
            <Menu.Target>
              <Button variant="light" rightSection={<IconChevronDown size={14} />}>Más</Button>
            </Menu.Target>
            <Menu.Dropdown>
              {secundarias.map((a) => (
                <Menu.Item key={a.key} leftSection={<a.icon size={14} />} color={a.key === 'anular' ? 'red' : undefined}
                  onClick={() => setAccion(a.key as Accion)}>
                  {a.label}
                </Menu.Item>
              ))}
            </Menu.Dropdown>
          </Menu>
        )}
      </Group>

      {accion === 'asignar' && <AsignarTicketModal ticket={t} onClose={cerrar} />}
      {accion === 'resolver' && <ResolverTicketModal ticket={t} onClose={cerrar} />}
      {accion === 'escalar' && <EscalarTicketModal ticket={t} onClose={cerrar} />}
      <MotivoModal
        opened={accion === 'preguntar'}
        onClose={cerrar}
        title={`Pedir información en ${t.folio}`}
        descripcion="La pregunta va a la conversación y el SLA queda en pausa hasta que el usuario responda."
        confirmLabel="Enviar la pregunta"
        etiqueta="Pregunta"
        placeholder="Ej.: ¿Qué mensaje de error le sale al abrir el programa?"
        cargando={pedirInformacion.isPending}
        onConfirm={(pregunta) => pedirInformacion.mutate({ id: t.id, pregunta }, { onSuccess: cerrar })}
      />
      <MotivoModal
        opened={accion === 'anular'}
        onClose={cerrar}
        title={`Anular ${t.folio}`}
        descripcion="Duplicado o mal registrado: el ticket deja de atenderse y el usuario verá el motivo."
        confirmLabel="Anular"
        destructiva
        cargando={anular.isPending}
        onConfirm={(motivo) => anular.mutate({ id: t.id, motivo }, { onSuccess: cerrar })}
      />
      <FormModal opened={accion === 'equipo'} onClose={cerrar} title={`Equipo de ${t.folio}`} submitLabel="Guardar"
        submitting={vincularBien.isPending}
        onSubmit={(e) => { e.preventDefault(); vincularBien.mutate({ id: t.id, bienId: equipo }, { onSuccess: cerrar }) }}>
        <BuscarEquipoSelect label="Equipo" soloPrincipales={false} placeholder="Código, serie o modelo"
          value={equipo} onChange={setEquipo}
          textoInicial={t.bien ? `${t.bien.codigo_institucional} · ${[t.bien.tipo, t.bien.modelo].filter(Boolean).join(' ')}` : undefined} />
      </FormModal>
    </>
  )
}
