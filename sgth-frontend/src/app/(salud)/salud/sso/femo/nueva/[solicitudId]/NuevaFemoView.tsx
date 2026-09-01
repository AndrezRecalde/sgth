'use client'

import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Stepper, Button, Group, Card, Text, Alert, Badge, Skeleton } from '@mantine/core'
import {
  IconUser, IconBriefcase, IconStethoscope,
  IconArrowLeft, IconArrowRight, IconCheck,
  IconInfoCircle, IconStretching,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { FemoPaso1 } from
  '@/features/dispensario/components/femo/FemoPaso1'
import { FemoPasoExamenFisico } from
  '@/features/dispensario/components/femo/FemoPasoExamenFisico'
import { FemoPaso2 } from
  '@/features/dispensario/components/femo/FemoPaso2'
import { FemoPaso3 } from
  '@/features/dispensario/components/femo/FemoPaso3'
import { useCrearFemo } from
  '@/features/dispensario/hooks/useFemo'
import { useFemoWizardState } from
  '@/features/dispensario/hooks/useFemoWizardState'
import { useSolicitudDetalle } from
  '@/features/dispensario/hooks/useSolicitudCertificacion'
import { DictamenMedicoModal } from
  '@/features/dispensario/components/DictamenMedicoModal'
import type { FichaBaseForm } from '@/features/dispensario/schemas/femo.schema'
import api from '@/lib/axios'
import { fromDateValue } from '@/lib/fecha'
import { PageHeader, PageShell } from '@/components/ui'

interface Props {
  solicitudId: string
}

const TIPO_FICHA_POR_EVENTO: Record<string, FichaBaseForm['tipo_ficha']> = {
  ingreso:   'ingreso',
  reintegro: 'reintegro',
  periodica: 'periodica',
  retiro:    'retiro',
  especial:  'ingreso',
}

const TIPO_EVENTO_LABELS: Record<string, string> = {
  ingreso:   'Ingreso / Pre-ocupacional',
  reintegro: 'Reintegro',
  periodica: 'Periódica',
  retiro:    'Retiro',
  especial:  'Especial',
}

export function NuevaFemoView({ solicitudId }: Props) {
  const router          = useRouter()
  const crear           = useCrearFemo()

  const solicitudIdNum = Number(solicitudId)

  const [dictamenOpened,
    { open: abrirDictamen, close: cerrarDictamen }] =
    useDisclosure(false)
  const [fichaGuardadaId, setFichaGuardadaId] =
    useState<number | null>(null)
  const [puestoId, setPuestoId] = useState<number | null>(null)

  const wizard = useFemoWizardState({
    tipo_ficha:         'ingreso',
    aptitud:            'apto',
    grupo_embarazada:   false,
    grupo_discapacidad: false,
    fecha_evaluacion:   fromDateValue(new Date()),
  })
  const { active, setActive, fichaData, setFichaData } = wizard

  const {
    data: solicitudDetalle,
    isFetched: solicitudDetalleFetched,
    isError: solicitudError,
  } = useSolicitudDetalle(Number.isNaN(solicitudIdNum) ? null : solicitudIdNum)

  // El FEMO siempre nace de una solicitud de RRHH (reclutamiento o expediente);
  // el médico no puede crear una ficha de forma independiente. Enfermería debe
  // registrar los signos vitales (Atención SSO) antes de continuar.
  useEffect(() => {
    if (
      Number.isNaN(solicitudIdNum) ||
      solicitudError ||
      (solicitudDetalleFetched && solicitudDetalle && !solicitudDetalle.constantes_vitales)
    ) {
      router.replace('/salud/sso')
    }
  }, [solicitudIdNum, solicitudError, solicitudDetalleFetched, solicitudDetalle, router])

  useEffect(() => {
    if (!solicitudDetalle) return
    const tipoEvento = solicitudDetalle.tipo_evento
    const cedula     = solicitudDetalle.cedula_paciente
    const esIngreso  = tipoEvento === 'ingreso'

    setFichaData(prev => ({
      ...prev,
      tipo_ficha:     TIPO_FICHA_POR_EVENTO[tipoEvento] ?? prev.tipo_ficha,
      numero_archivo: cedula,
    }))

    if (solicitudDetalle.constantes_vitales) {
      const cv = solicitudDetalle.constantes_vitales
      wizard.setConstantesData({
        temperatura_c:           cv.temperatura_c ?? null,
        presion_sistolica:       cv.presion_sistolica ?? null,
        presion_diastolica:      cv.presion_diastolica ?? null,
        frecuencia_cardiaca:     cv.frecuencia_cardiaca ?? null,
        frecuencia_respiratoria: cv.frecuencia_respiratoria ?? null,
        saturacion_oxigeno:      cv.saturacion_oxigeno ?? null,
        peso_kg:                 cv.peso_kg ?? null,
        talla_cm:                cv.talla_cm ?? null,
        imc:                     cv.imc ?? null,
        glucosa:                 cv.glucosa ?? null,
      })
    }

    /**
     * El puesto que se evalúa, de lo más específico a lo más general.
     *
     * El del aspirante va primero por reclutamiento express: ahí el contenedor
     * es permanente y no tiene puesto, cada aspirante trae el suyo. Leer solo
     * el de la convocatoria dejaba a todos los aspirantes express sin puesto y
     * obligaba al médico a teclearlo.
     */
    const puesto =
      solicitudDetalle.postulante?.puesto ??
      solicitudDetalle.servidor?.puesto ??
      solicitudDetalle.convocatoria?.puesto ??
      null

    if (puesto) {
      setFichaData(prev => ({
        ...prev,
        puesto_id:           puesto.id,
        // Copia para mostrar mientras se llena. Al guardar, el backend vuelve a
        // sellar el nombre y el CIUO desde `puesto_id`, que es la fuente.
        puesto_trabajo:      puesto.cargo?.nombre ?? prev.puesto_trabajo,
        puesto_trabajo_ciuo: puesto.cargo?.codigo_ciuo ?? prev.puesto_trabajo_ciuo,
      }))
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setPuestoId(puesto.id)
    }

    if (solicitudDetalle.postulante?.id) {
      setFichaData(prev => ({
        ...prev,
        postulante_id: solicitudDetalle.postulante!.id,
      }))
    }

    if (solicitudDetalle.servidor?.id) {
      setFichaData(prev => ({
        ...prev,
        servidor_id: solicitudDetalle.servidor!.id,
      }))
    }

    if (esIngreso) {
      // El candidato de ingreso todavía no tiene expediente, así que se le abre
      // historia clínica por cédula.
      api.post('/dispensario/historias-clinicas/crear-por-cedula', {
        cedula_paciente: cedula,
        tipo_paciente:   'candidato',
      }).catch(() => {})
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [solicitudDetalle])

  /**
   * Sexo del paciente. Decide qué bloque reproductivo del MSP se muestra.
   * Puede venir vacío: la columna no está poblada para toda la plantilla.
   */
  const sexoPaciente =
    solicitudDetalle?.postulante?.genero ??
    solicitudDetalle?.servidor?.genero ??
    null

  /** Grupo sanguíneo del expediente. La sección A lo muestra, no lo pide. */
  const tipoSangrePaciente =
    solicitudDetalle?.postulante?.tipo_sangre ??
    solicitudDetalle?.servidor?.tipo_sangre ??
    null

  const pasos = [
    {
      label:       'Información del paciente',
      description: 'Datos, signos vitales y antecedentes',
      icon:        <IconUser size={16} />,
    },
    {
      label:       'Examen físico',
      description: 'Examen físico regional',
      icon:        <IconStretching size={16} />,
    },
    {
      label:       'Evaluación laboral',
      description: 'Factores de riesgo y empleos anteriores',
      icon:        <IconBriefcase size={16} />,
    },
    {
      label:       'Diagnóstico y cierre',
      description: 'Exámenes, CIE-10 y aptitud médica',
      icon:        <IconStethoscope size={16} />,
    },
  ]

  /**
   * Qué falta para poder avanzar del paso actual.
   *
   * Devuelve la lista, no un booleano: antes el botón «Siguiente» simplemente
   * aparecía apagado y el médico tenía que adivinar cuál de los campos de la
   * sección A lo estaba bloqueando.
   */
  const faltantes = (): string[] => {
    if (active !== 0) return []

    const falta: string[] = []
    if (!fichaData.servidor_id && !fichaData.postulante_id) {
      falta.push('identificar al paciente')
    }
    if (!fichaData.fecha_evaluacion) falta.push('la fecha de evaluación')
    if (!fichaData.tipo_ficha) falta.push('el tipo de evaluación')
    return falta
  }

  const pendientes = faltantes()

  const handleGuardar = () => {
    const payload = wizard.construirPayload()
    if (!payload) return

    crear.mutate(payload, {
      onSuccess: (ficha) => {
        setFichaGuardadaId(ficha?.id ?? null)
        abrirDictamen()
      },
    })
  }

  if (!solicitudDetalle) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={400} radius="lg" />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title="Nueva ficha FEMO"
        description="Ficha de evaluación médica ocupacional"
      />

      <Alert
        color="blue"
        variant="light"
        icon={<IconInfoCircle size={16} />}
      >
        <Group gap="xs" wrap="wrap">
          <Text size="xs">Solicitud de Talento Humano —</Text>
          <Badge size="xs" variant="light" color="blue">
            {TIPO_EVENTO_LABELS[solicitudDetalle.tipo_evento]}
          </Badge>
          <Text size="xs" fw={600}>
            {solicitudDetalle.nombres_paciente}
          </Text>
          <Text size="xs" c="dimmed" ff="monospace">
            {solicitudDetalle.cedula_paciente}
          </Text>
        </Group>
      </Alert>

      <Stepper active={active} onStepClick={setActive} size="sm">
        {pasos.map((paso, i) => (
          <Stepper.Step
            key={i}
            label={paso.label}
            description={paso.description}
            icon={paso.icon}
          />
        ))}
      </Stepper>

      <Card withBorder radius="lg" p="lg">
        {active === 0 && (
          <FemoPaso1
            fichaData={wizard.fichaData}
            constantesData={wizard.constantesData}
            antecedentes={wizard.antecedentes}
            antecedenteReproductivo={wizard.antecedenteReproductivo}
            consumoSustancias={wizard.consumoSustancias}
            onFichaChange={wizard.setFichaData}
            onAntecedentesChange={wizard.setAntecedentes}
            onAntecedenteReproductivoChange={wizard.setAntecedenteReproductivo}
            onConsumoSustanciasChange={wizard.setConsumoSustancias}
            sexo={sexoPaciente}
            tipoSangre={tipoSangrePaciente}
            cedula={solicitudDetalle.cedula_paciente}
          />
        )}
        {active === 1 && (
          <FemoPasoExamenFisico
            examenFisico={wizard.examenFisico}
            onChange={wizard.setExamenFisico}
          />
        )}
        {active === 2 && (
          <FemoPaso2
            fichaData={wizard.fichaData}
            puestoId={puestoId}
            actividadesRiesgo={wizard.actividadesRiesgo}
            factoresRiesgo={wizard.factoresRiesgo}
            empleosAnteriores={wizard.empleosAnteriores}
            onFichaChange={wizard.setFichaData}
            onActividadesChange={wizard.setActividadesRiesgo}
            onFactoresChange={wizard.setFactoresRiesgo}
            onEmpleosChange={wizard.setEmpleosAnteriores}
          />
        )}
        {active === 3 && (
          <FemoPaso3
            fichaData={wizard.fichaData}
            examenes={wizard.examenes}
            diagnosticos={wizard.diagnosticos}
            onFichaChange={wizard.setFichaData}
            onExamenesChange={wizard.setExamenes}
            onDiagnosticosChange={wizard.setDiagnosticos}
          />
        )}
      </Card>

      <Group justify="space-between">
        <Button
          variant="default"
          leftSection={<IconArrowLeft size={14} />}
          onClick={() => {
            if (active === 0) {
              router.push('/salud/sso')
            } else {
              setActive(a => a - 1)
            }
          }}
        >
          {active === 0 ? 'Cancelar' : 'Anterior'}
        </Button>

        {active < 3 ? (
          <Group gap="sm" wrap="nowrap">
            {pendientes.length > 0 && (
              <Text size="xs" c="dimmed" ta="right" maw={320}>
                Para continuar falta {pendientes.join(', ')}.
              </Text>
            )}
            <Button
              variant="light"
              rightSection={<IconArrowRight size={14} />}
              disabled={pendientes.length > 0}
              onClick={() => setActive(a => a + 1)}
            >
              Siguiente
            </Button>
          </Group>
        ) : (
          <Button
            variant="light"
            leftSection={<IconCheck size={14} />}
            loading={crear.isPending}
            onClick={handleGuardar}
          >
            Guardar FEMO y emitir dictamen
          </Button>
        )}
      </Group>

      <DictamenMedicoModal
        opened={dictamenOpened}
        onClose={() => {
          cerrarDictamen()
          router.push('/salud/sso')
        }}
        solicitud={solicitudDetalle}
        fichaFemoId={fichaGuardadaId}
      />
    </PageShell>
  )
}
