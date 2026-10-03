'use client'

import { Text, Skeleton } from '@mantine/core'
import { ResumenPaciente } from './ResumenPaciente'
import { useHistorialTriaje, useRegistrarTriaje } from '../hooks/useTriaje'
import { UltimoTriajeReferencia } from './UltimoTriajeReferencia'
import { TomasPreviasTriaje } from './TomasPreviasTriaje'
import { FormularioSignosVitales } from './FormularioSignosVitales'
import { EDAD_ADULTO, edadEnAnios } from '../constants/signosVitales'
import type { AgendaMedica } from '../services/agendaService'
import type { Triaje } from '../services/triajeService'

interface Props {
  turno:      AgendaMedica
  onCreado:   (triaje: Triaje) => void
  onCancelar: () => void
  /** «Cancelar» no cancela el turno: donde el paciente sigue en la cola se dice. */
  textoCancelar?: string
}

/**
 * Rehacer una toma arranca con las cifras de la vigente: para corregir una
 * mal tecleada había que volver a escribir las ocho. Los decimales llegan
 * del backend como cadena («70.50»), de ahí el `Number`.
 */
function desdeToma(toma: Triaje | undefined) {
  if (!toma) return undefined
  return {
    peso_kg:                 Number(toma.peso_kg),
    talla_cm:                Number(toma.talla_cm),
    presion_sistolica:       Number(toma.presion_sistolica),
    presion_diastolica:      Number(toma.presion_diastolica),
    frecuencia_cardiaca:     Number(toma.frecuencia_cardiaca),
    frecuencia_respiratoria: Number(toma.frecuencia_respiratoria),
    temperatura_c:           Number(toma.temperatura_c),
    saturacion_oxigeno:      Number(toma.saturacion_oxigeno),
    glucosa:                 toma.glucosa == null ? null : Number(toma.glucosa),
    observaciones_enfermera: '',
  }
}

/** El triaje de un turno: signos vitales antes de pasar con el profesional. */
export function TriajeForm({ turno, onCreado, onCancelar, textoCancelar = 'Cancelar' }: Props) {
  const registrar = useRegistrarTriaje()
  const historial = useHistorialTriaje(turno.id)

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`
  const edad = edadEnAnios(
    esServidor ? turno.servidor?.fecha_nacimiento : turno.carga_familiar?.fecha_nacimiento,
  )

  const encabezado = (
    <ResumenPaciente
      nombre={nombrePaciente}
      esServidor={esServidor}
      etiqueta={turno.tipo_atencion === 'medicina_general' ? 'Medicina General' : 'Odontología'}
      detalle={
        <Text size="xs" c="dimmed">
          <Text span ff="monospace" inherit>{turno.folio}</Text>
          {edad !== null && ` · ${edad} ${edad === 1 ? 'año' : 'años'}`}
        </Text>
      }
    >
      {/* Lo que se escribió al crear el turno: orienta qué mirar al medir. */}
      {turno.motivo_solicitud && (
        <Text size="xs">
          <Text span c="dimmed" inherit>Motivo: </Text>
          {turno.motivo_solicitud}
        </Text>
      )}
    </ResumenPaciente>
  )

  // Hasta saber si hay una toma previa: los valores iniciales de un
  // formulario se fijan al montarlo.
  if (historial.isLoading) return <Skeleton height={480} radius="lg" />

  return (
    <FormularioSignosVitales
      encabezado={encabezado}
      contexto={
        <>
          <TomasPreviasTriaje agendaId={turno.id} />
          <UltimoTriajeReferencia agendaId={turno.id} />
        </>
      }
      esMenor={edad !== null && edad < EDAD_ADULTO}
      consecuencia={{
        critico:  'El turno quedará marcado como crítico en la cola. Valore si el paciente puede esperar.',
        atencion: 'El turno quedará marcado en la cola para que el profesional lo vea.',
      }}
      textoEnviar="Registrar triaje"
      valoresIniciales={desdeToma(historial.data?.at(-1))}
      textoCancelar={textoCancelar}
      enviando={registrar.isPending}
      enviar={(data) =>
        registrar.mutateAsync({ agendaId: turno.id, data }).then(onCreado)
      }
      onCancelar={onCancelar}
    />
  )
}
