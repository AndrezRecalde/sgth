'use client'

import { useState } from 'react'
import { Button, Group } from '@mantine/core'
import {
  IconCalendarEvent, IconCopy, IconEdit, IconPin, IconPinnedOff, IconSend, IconTrash, IconX,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { confirmar, MotivoModal, notificar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { hoyIso } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { useMutacionesComunicado } from '../hooks/useMutacionesComunicado'
import { usePermisosComunicados } from '../hooks/usePermisosComunicados'
import type { Comunicado } from '../services/tipos'
import { fechaPublicacion } from '../utils/presentacion'
import { FechaFinModal } from './FechaFinModal'

/**
 * Lo que se puede hacer con una publicación según su estado y los permisos de
 * quien mira (docs/diseno-noticias-comunicados.md, 6.5). El backend vuelve a
 * comprobar las dos cosas: esto solo evita ofrecer lo que daría un 403 o un 422.
 */
export function ComunicadoAcciones({ comunicado: c }: { comunicado: Comunicado }) {
  const router = useRouter()
  const { redacta, publica } = usePermisosComunicados()
  const { publicar, retirar, duplicar, eliminar, actualizarPublicacion } = useMutacionesComunicado()
  const [retirando, setRetirando] = useState(false)
  const [cambiandoFin, setCambiandoFin] = useState(false)

  const borrador = c.estado === 'borrador'
  const publicado = c.estado === 'publicado'

  const confirmarPublicacion = () => {
    // El backend lo rechaza igual; así se dice antes de pedir la confirmación.
    if (!c.publicado_hasta) {
      notificar.aviso('Falta la fecha de fin', 'Edite el borrador y elija hasta cuándo se verá en el Portal.')
      return
    }
    const futura = c.publicado_desde && c.publicado_desde.substring(0, 10) > hoyIso()
    confirmar({
      title: futura ? 'Programar la publicación' : 'Publicar',
      message: (
        <>
          {futura
            ? <>Saldrá en el Portal el <b>{fechaPublicacion(c.publicado_desde)}</b></>
            : <>Saldrá ya en el Portal</>}
          {' '}y se verá hasta el <b>{fechaPublicacion(c.publicado_hasta)}</b>.
          Hoy la verían <b>{plural(c.total_destinatarios ?? 0, 'persona', 'personas')}</b>.
          {c.requiere_confirmacion && ' Cada una deberá confirmar que la leyó.'}
          {' '}Lo publicado ya no se edita: para corregirlo hay que retirarlo y duplicarlo.
        </>
      ),
      confirmLabel: futura ? 'Programar' : 'Publicar',
      onConfirm: () => publicar.mutate(c.id),
    })
  }

  const alternarFijado = () => actualizarPublicacion.mutate({ id: c.id, cambios: { fijado: !c.fijado } })

  const confirmarEliminacion = () => confirmar({
    title: 'Eliminar borrador',
    message: <>Se eliminará <b>{c.titulo}</b> con sus documentos. No se puede deshacer.</>,
    confirmLabel: 'Eliminar',
    destructiva: true,
    onConfirm: () => eliminar.mutate(c.id, { onSuccess: () => router.push(ROUTES.SGTH.COMUNICADOS) }),
  })

  const copiar = () => duplicar.mutate(c.id, { onSuccess: (copia) => router.push(ROUTES.SGTH.COMUNICADO_EDITAR(copia.id)) })

  return (
    <Group gap="xs">
      {borrador && redacta && (
        <Button variant="default" leftSection={<IconEdit size={16} />} onClick={() => router.push(ROUTES.SGTH.COMUNICADO_EDITAR(c.id))}>
          Editar
        </Button>
      )}
      {borrador && publica && (
        <Button variant="light" leftSection={<IconSend size={16} />} onClick={confirmarPublicacion} loading={publicar.isPending}>
          Publicar
        </Button>
      )}
      {publicado && publica && (
        <>
          <Button
            variant="default"
            leftSection={c.fijado ? <IconPinnedOff size={16} /> : <IconPin size={16} />}
            onClick={alternarFijado}
            loading={actualizarPublicacion.isPending}
          >
            {c.fijado ? 'Desfijar' : 'Fijar'}
          </Button>
          <Button variant="default" leftSection={<IconCalendarEvent size={16} />} onClick={() => setCambiandoFin(true)}>
            Fecha de fin
          </Button>
        </>
      )}
      {redacta && (
        <Button variant="default" leftSection={<IconCopy size={16} />} onClick={copiar} loading={duplicar.isPending}>
          Duplicar
        </Button>
      )}
      {publicado && publica && (
        <Button variant="light" color="red" leftSection={<IconX size={16} />} onClick={() => setRetirando(true)}>
          Retirar
        </Button>
      )}
      {borrador && redacta && (
        <Button variant="light" color="red" leftSection={<IconTrash size={16} />} onClick={confirmarEliminacion} loading={eliminar.isPending}>
          Eliminar
        </Button>
      )}

      <MotivoModal
        opened={retirando}
        onClose={() => setRetirando(false)}
        title="Retirar la publicación"
        descripcion="Deja de verse en el Portal y queda en el historial con este motivo. Para corregirla, duplíquela después."
        etiqueta="Motivo del retiro"
        placeholder="Ej: La fecha del feriado estaba mal"
        confirmLabel="Retirar"
        destructiva
        cargando={retirar.isPending}
        onConfirm={(motivo) => retirar.mutate({ id: c.id, motivo }, { onSuccess: () => setRetirando(false) })}
      />
      {cambiandoFin && <FechaFinModal comunicado={c} opened onClose={() => setCambiandoFin(false)} />}
    </Group>
  )
}
