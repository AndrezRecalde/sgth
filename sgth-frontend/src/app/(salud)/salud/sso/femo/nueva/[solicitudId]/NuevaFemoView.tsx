'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group, Text, Alert, Skeleton } from '@mantine/core'
import { IconCheck, IconInfoCircle, IconDeviceFloppy } from '@tabler/icons-react'
import { FemoAsistente } from '@/features/dispensario/components/femo/FemoAsistente'
import { useFemoDetalle } from '@/features/dispensario/hooks/useFemo'
import { useGuardarFemo } from '@/features/dispensario/hooks/useGuardarFemo'
import { useFemoWizardState } from '@/features/dispensario/hooks/useFemoWizardState'
import { useFemoDesdeSolicitud } from '@/features/dispensario/hooks/useFemoDesdeSolicitud'
import { useCambiosSinGuardar } from '@/features/dispensario/hooks/useCambiosSinGuardar'
import { useSolicitudDetalle } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { APTITUD_OPTIONS } from '@/features/dispensario/services/femoOptions'
import { etiquetaTipoEvento } from '@/features/dispensario/services/solicitudCertificacionService'
import { fromDateValue } from '@/lib/fecha'
import { confirmar, notificar, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'

interface Props {
  solicitudId: string
}

/**
 * La ficha FEMO de una solicitud: se crea al iniciarla y se retoma con
 * «Continuar FEMO» hasta que el médico emite el dictamen.
 */
export function NuevaFemoView({ solicitudId }: Props) {
  const router         = useRouter()
  const solicitudIdNum = Number(solicitudId)

  const wizard = useFemoWizardState({
    tipo_ficha:         'ingreso',
    aptitud:            null,
    grupo_embarazada:   false,
    grupo_discapacidad: false,
    fecha_evaluacion:   fromDateValue(new Date()),
  })

  const {
    data: solicitud,
    isFetching: solicitudCargando,
    isError: solicitudError,
  } = useSolicitudDetalle(Number.isNaN(solicitudIdNum) ? null : solicitudIdNum)

  const { data: fichaGuardada } = useFemoDetalle(solicitud?.ficha_femo_id ?? null)
  const { puestoId, listo } = useFemoDesdeSolicitud(solicitud, fichaGuardada, wizard)
  const cambios = useCambiosSinGuardar(JSON.stringify(wizard.construirPayload()), listo)

  const guardado = useGuardarFemo({
    solicitudId:      solicitudIdNum,
    construirPayload: wizard.construirPayload,
  })

  // La ficha se retoma con su id, para que guardar actualice en vez de crear.
  useEffect(() => {
    if (fichaGuardada) guardado.setFichaId(fichaGuardada.id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fichaGuardada])

  // El FEMO siempre nace de una solicitud de Talento Humano en curso y con el
  // triaje de Enfermería hecho. Se espera a que termine de cargar para no
  // rebotar con un estado viejo en caché justo después de «Iniciar».
  useEffect(() => {
    if (
      Number.isNaN(solicitudIdNum) ||
      solicitudError ||
      (!solicitudCargando && solicitud && (
        !solicitud.constantes_vitales || solicitud.estado !== 'en_proceso'
      ))
    ) {
      router.replace(ROUTES.SALUD.SSO)
    }
  }, [solicitudIdNum, solicitudError, solicitudCargando, solicitud, router])

  const guardarBorrador = () => {
    void guardado.guardar().then((id) => { if (id) cambios.marcarGuardado() })
  }

  const volver = () => {
    if (!cambios.sucio) {
      router.push(ROUTES.SALUD.SSO)
      return
    }
    confirmar({
      title: 'Hay cambios sin guardar',
      message: 'Si vuelve a la bandeja se perderá lo que no haya guardado como borrador.',
      confirmLabel: 'Salir sin guardar',
      destructiva: true,
      onConfirm: () => router.push(ROUTES.SALUD.SSO),
    })
  }

  // El dictamen es la aptitud de la sección L; aquí solo se confirma.
  const emitir = () => {
    const aptitud = APTITUD_OPTIONS.find(o => o.value === wizard.fichaData.aptitud)?.label
    if (!aptitud) {
      notificar.aviso('Falta la aptitud', 'Marque la aptitud médica en la sección L.')
      return
    }
    confirmar({
      title: 'Emitir dictamen',
      message: (
        <>
          El dictamen será <b>{aptitud}</b>, la aptitud marcada en la sección L.
          Al emitirlo la ficha queda cerrada y ya no se puede editar.
        </>
      ),
      confirmLabel: 'Emitir dictamen',
      onConfirm: () => {
        void guardado.emitirDictamen().then((cerrada) => {
          if (!cerrada) return
          cambios.marcarGuardado()
          router.push(ROUTES.SALUD.SSO)
        })
      },
    })
  }

  if (!solicitud) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={400} radius="lg" />
      </PageShell>
    )
  }

  const persona = solicitud.postulante ?? solicitud.servidor

  return (
    <PageShell>
      <PageHeader
        title={solicitud.ficha_femo_id ? 'Ficha FEMO (borrador)' : 'Nueva ficha FEMO'}
        description="Ficha de evaluación médica ocupacional"
        onBack={volver}
      />

      <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
        <Group gap="xs" wrap="wrap">
          <Text size="xs">Solicitud de Talento Humano —</Text>
          <StatusBadge size="xs">{etiquetaTipoEvento(solicitud.tipo_evento)}</StatusBadge>
          <Text size="xs" fw={600}>{solicitud.nombres_paciente}</Text>
          <Text size="xs" c="dimmed" ff="monospace">{solicitud.cedula_paciente}</Text>
        </Group>
      </Alert>

      <FemoAsistente
        wizard={wizard}
        sexo={persona?.genero ?? null}
        tipoSangre={persona?.tipo_sangre ?? null}
        cedula={solicitud.cedula_paciente}
        puestoId={puestoId}
        onSalir={volver}
        etiquetaSalir="Volver a la bandeja"
        accionesSecundarias={
          // Se puede guardar en cualquier paso: la ficha queda como borrador
          // de la solicitud y «Continuar FEMO» la retoma.
          <Button
            variant="subtle"
            leftSection={<IconDeviceFloppy size={14} />}
            loading={guardado.guardando && !guardado.emitiendo}
            onClick={guardarBorrador}
          >
            Guardar borrador
          </Button>
        }
        accionFinal={
          <Button leftSection={<IconCheck size={14} />} loading={guardado.emitiendo} onClick={emitir}>
            Emitir dictamen
          </Button>
        }
      />
    </PageShell>
  )
}
