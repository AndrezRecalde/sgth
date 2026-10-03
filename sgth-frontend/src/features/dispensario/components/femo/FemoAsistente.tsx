'use client'

import type { ReactNode } from 'react'
import { Stepper, Button, Group, Card, Text } from '@mantine/core'
import {
  IconUser, IconBriefcase, IconStethoscope, IconStretching,
  IconArrowLeft, IconArrowRight,
} from '@tabler/icons-react'
import type { useFemoWizardState } from '../../hooks/useFemoWizardState'
import type { SexoPaciente } from '../../services/solicitudCertificacionService'
import { FemoPaso1 } from './FemoPaso1'
import { FemoPasoExamenFisico } from './FemoPasoExamenFisico'
import { FemoPaso2 } from './FemoPaso2'
import { FemoPaso3 } from './FemoPaso3'
import classes from './FemoAsistente.module.css'

interface Props {
  wizard:      ReturnType<typeof useFemoWizardState>
  sexo?:       SexoPaciente
  tipoSangre?: string | null
  cedula?:     string
  puestoId:    number | null
  /** Qué hace el botón de la izquierda en el primer paso. */
  onSalir:       () => void
  etiquetaSalir: string
  /** Junto al de salir: «Guardar borrador». */
  accionesSecundarias?: ReactNode
  /** El botón del último paso: «Emitir dictamen». */
  accionFinal: ReactNode
}

const PASOS = [
  { label: 'Información del paciente', description: 'Secciones A a E', icon: <IconUser size={16} /> },
  { label: 'Examen físico',            description: 'Sección F',       icon: <IconStretching size={16} /> },
  { label: 'Evaluación laboral',       description: 'Secciones G a I', icon: <IconBriefcase size={16} /> },
  { label: 'Diagnóstico y cierre',     description: 'Secciones J a N', icon: <IconStethoscope size={16} /> },
]

const ULTIMO = PASOS.length - 1

/**
 * Los cuatro pasos del FEMO con su navegación.
 *
 * Lo comparten la ficha nueva y la que se retoma desde la bandeja. Antes el
 * detalle tenía su propia copia del asistente, que se había quedado atrás.
 */
export function FemoAsistente({
  wizard, sexo, tipoSangre, cedula, puestoId,
  onSalir, etiquetaSalir, accionesSecundarias, accionFinal,
}: Props) {
  const { active, setActive, fichaData } = wizard

  /**
   * Qué falta para salir del primer paso. Devuelve la lista, no un booleano:
   * con el botón apagado el médico tenía que adivinar qué lo bloqueaba.
   */
  const pendientes: string[] = []
  if (!fichaData.servidor_id && !fichaData.postulante_id) pendientes.push('identificar al paciente')
  if (!fichaData.fecha_evaluacion) pendientes.push('la fecha de atención')
  const bloqueado = active === 0 && pendientes.length > 0

  // Hacia atrás siempre; hacia adelante, solo si el paso 1 está completo. Antes
  // un clic en la cabecera del Stepper se saltaba esa validación.
  const irA = (paso: number) => {
    if (paso > active && pendientes.length > 0) return
    setActive(paso)
  }

  return (
    <>
      <Stepper active={active} onStepClick={irA} size="sm">
        {PASOS.map((paso) => (
          <Stepper.Step key={paso.label} label={paso.label} description={paso.description} icon={paso.icon} />
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
            sexo={sexo}
            tipoSangre={tipoSangre}
            cedula={cedula}
          />
        )}
        {active === 1 && (
          <FemoPasoExamenFisico
            examenFisico={wizard.examenFisico}
            onChange={wizard.setExamenFisico}
            fichaData={wizard.fichaData}
            onFichaChange={wizard.setFichaData}
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
        {active === ULTIMO && (
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

      <Group justify="space-between" className={classes.pie}>
        <Group gap="sm">
          <Button
            variant="default"
            leftSection={<IconArrowLeft size={14} />}
            onClick={() => (active === 0 ? onSalir() : setActive(a => a - 1))}
          >
            {active === 0 ? etiquetaSalir : 'Anterior'}
          </Button>
          {accionesSecundarias}
        </Group>

        {active < ULTIMO ? (
          <Group gap="sm" wrap="nowrap">
            {bloqueado && (
              <Text size="xs" c="dimmed" ta="right" maw={320}>
                Para continuar falta {pendientes.join(', ')}.
              </Text>
            )}
            <Button
              variant="light"
              rightSection={<IconArrowRight size={14} />}
              disabled={bloqueado}
              onClick={() => setActive(a => a + 1)}
            >
              Siguiente
            </Button>
          </Group>
        ) : accionFinal}
      </Group>
    </>
  )
}
