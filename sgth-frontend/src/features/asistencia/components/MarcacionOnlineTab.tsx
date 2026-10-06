'use client'

import { useState } from 'react'
import { Grid } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { EmptyState, SectionCard, confirmar, notificar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useMarcacionOnline } from '../hooks/useMarcacionOnline'
import { useUbicacion } from '../hooks/useUbicacion'
import {
  accionesDelDia,
  motivoParaConfirmar,
  siguienteAccion,
  type AccionMarcacion,
  type ClaveAccion,
} from '../utils/marcacionOnline'
import { AccionesMarcacion } from './AccionesMarcacion'
import { ProgresoHoy } from './ProgresoHoy'
import { UbicacionEstado } from './UbicacionEstado'

/**
 * La marcación en línea: registrar las cuatro marcas del día con la ubicación
 * del momento, y ver cuáles ya están.
 *
 * Hace falta el permiso `marcar-en-linea` (TI lo asigna a pedido de Talento
 * Humano) y tener la marcación habilitada. El menú ya oculta la pantalla sin
 * el permiso; los avisos de abajo son para quien entra por la URL.
 */
export function MarcacionOnlineTab() {
  const { usuario, hasPermiso } = useAuth()
  const autorizado = hasPermiso('marcar-en-linea')
  const servidor = usuario?.servidor
  const habilitado = !!servidor?.cedula && !!servidor.puede_marcar
  const activa = autorizado && habilitado

  const ubicacion = useUbicacion(activa)
  const { hoy, registrar } = useMarcacionOnline(activa)
  const [enCurso, setEnCurso] = useState<ClaveAccion | null>(null)

  const acciones = accionesDelDia(hoy.data)
  const siguiente = siguienteAccion(hoy.data, acciones)

  const ejecutar = async (accion: AccionMarcacion) => {
    setEnCurso(accion.clave)
    try {
      // La ubicación se pide ahora, no la que se leyó al abrir la página.
      const coordenadas = await ubicacion.actualizar().catch((error: Error) => {
        notificar.error('Ubicación no disponible', `${error.message} La marcación no se registró.`)
        return null
      })
      if (coordenadas) {
        // El error de la petición ya lo avisa la mutación.
        await registrar.mutateAsync({ accion, ubicacion: coordenadas }).catch(() => {})
      }
    } finally {
      setEnCurso(null)
    }
  }

  const marcar = (accion: AccionMarcacion) => {
    const motivo = motivoParaConfirmar(accion, hoy.data, siguiente)
    if (!motivo) {
      void ejecutar(accion)
      return
    }
    confirmar({
      title: `¿Registrar «${accion.etiqueta}»?`,
      message: motivo,
      confirmLabel: 'Registrar',
      onConfirm: () => void ejecutar(accion),
    })
  }

  if (!autorizado) {
    return (
      <EmptyState
        icon={IconLock}
        title="No tiene autorización para marcar en línea"
        description="Si su trabajo lo requiere, solicítela a Talento Humano."
      />
    )
  }

  if (!habilitado) {
    return (
      <EmptyState
        icon={IconLock}
        title="Su usuario no tiene la marcación biométrica habilitada"
        description="Talento Humano la habilita en su contrato."
      />
    )
  }

  return (
    <Grid gap="lg">
      <Grid.Col span={{ base: 12, md: 7 }}>
        <SectionCard
          title="Registrar marcación"
          description="Se guarda con su ubicación en el momento de pulsar."
          actions={
            <UbicacionEstado
              estado={ubicacion.estado}
              coordenadas={ubicacion.coordenadas}
              onReintentar={() => void ubicacion.actualizar().catch(() => {})}
            />
          }
        >
          <AccionesMarcacion
            acciones={acciones}
            siguiente={siguiente}
            enCurso={enCurso}
            bloqueadas={ubicacion.estado === 'denegada' || ubicacion.estado === 'no-disponible'}
            onMarcar={marcar}
          />
        </SectionCard>
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 5 }}>
        <SectionCard title="Progreso de hoy">
          <ProgresoHoy
            estado={hoy.data}
            acciones={acciones}
            siguiente={siguiente}
            cargando={hoy.isLoading}
            error={hoy.error}
            onReintentar={() => void hoy.refetch()}
          />
        </SectionCard>
      </Grid.Col>
    </Grid>
  )
}
