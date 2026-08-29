'use client'

import { useState } from 'react'
import { Anchor, Grid, Group, NumberInput, Select, Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { METODO_PLANIFICACION_OPTIONS } from '../../services/femoOptions'
import type { SexoPaciente } from '../../services/solicitudCertificacionService'
import type { AntecedenteReproductivoForm } from '../../schemas/femo.schema'

interface Props {
  data: Partial<AntecedenteReproductivoForm>
  onChange: (data: Partial<AntecedenteReproductivoForm>) => void
  /** Sexo del paciente. Si falta, se muestran ambos bloques. */
  sexo?: SexoPaciente
}

/**
 * Antecedentes reproductivos de la sección C.
 *
 * El formulario 028 tiene DOS bloques, no uno: gineco-obstétricos —que solo
 * aplican a mujeres— y antecedentes reproductivos masculinos, que se limitan a
 * exámenes realizados y método de planificación. Antes se mostraba un único
 * bloque mezclado, así que a un paciente hombre se le preguntaba por gestas,
 * partos y cesáreas.
 *
 * Se OCULTA lo que no aplica, no se bloquea, y por tres razones:
 *  - la columna `genero` no está poblada para toda la plantilla, y un dato
 *    ausente no puede dejar campos inalcanzables;
 *  - hay situaciones clínicas que no encajan en la casilla administrativa;
 *  - lo ya capturado nunca se borra al cambiar de bloque, solo deja de verse.
 *
 * De ahí el enlace para mostrar el otro bloque: la decisión final es del
 * médico, no del dato del expediente.
 */
export function FemoAntecedentesReproductivosSection({ data, onChange, sexo }: Props) {
  const contained = useContainedInput()
  const [mostrarOtro, setMostrarOtro] = useState(false)

  const set = (cambios: Partial<AntecedenteReproductivoForm>) =>
    onChange({ ...data, ...cambios })

  // Sin sexo registrado se muestran los dos: es preferible preguntar de más
  // que dejar al médico sin dónde registrar lo que encontró.
  const sexoDesconocido = sexo !== 'masculino' && sexo !== 'femenino'
  const verGineco = sexoDesconocido || sexo === 'femenino' || mostrarOtro
  const verMasculino = sexoDesconocido || sexo === 'masculino' || mostrarOtro

  /** Exámenes y planificación: los pide el impreso en los dos bloques. */
  const camposComunes = (
    <>
      <Grid.Col span={{ base: 12, md: 4 }}>
        <Select
          label="Método de planificación familiar"
          data={METODO_PLANIFICACION_OPTIONS}
          clearable
          {...contained}
          value={data.usa_metodo_planificacion ?? null}
          onChange={(v) => set({
            usa_metodo_planificacion:
              v as AntecedenteReproductivoForm['usa_metodo_planificacion'],
          })}
        />
      </Grid.Col>

      {data.usa_metodo_planificacion === 'si' && (
        <Grid.Col span={{ base: 12, md: 4 }}>
          <TextInput
            label="¿Cuál?"
            {...contained}
            value={data.metodo_planificacion_cual ?? ''}
            onChange={(e) => set({ metodo_planificacion_cual: e.currentTarget.value })}
          />
        </Grid.Col>
      )}

      <Grid.Col span={{ base: 12, md: 5 }}>
        <TextInput
          label="Exámenes realizados (¿cuál?)"
          {...contained}
          value={data.examenes_realizados ?? ''}
          onChange={(e) => set({ examenes_realizados: e.currentTarget.value })}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 3 }}>
        <NumberInput
          label="Hace cuánto (años)"
          min={0}
          {...contained}
          value={data.examenes_tiempo_anios ?? undefined}
          onChange={(v) => set({ examenes_tiempo_anios: v !== '' ? Number(v) : null })}
        />
      </Grid.Col>
    </>
  )

  return (
    <Stack gap="xs">
      <Group justify="space-between" align="baseline">
        <Text size="xs" fw={600} c="dimmed">
          {verGineco && verMasculino
            ? 'Antecedentes reproductivos'
            : verGineco
              ? 'Antecedentes gineco-obstétricos'
              : 'Antecedentes reproductivos masculinos'}
        </Text>

        {!sexoDesconocido && (
          <Anchor
            component="button"
            type="button"
            size="xs"
            onClick={() => setMostrarOtro((v) => !v)}
          >
            {mostrarOtro
              ? 'Mostrar solo lo que corresponde'
              : sexo === 'femenino'
                ? 'Mostrar también los antecedentes masculinos'
                : 'Mostrar también los gineco-obstétricos'}
          </Anchor>
        )}
      </Group>

      {sexoDesconocido && (
        <Text size="xs" c="dimmed">
          El expediente no registra el sexo del paciente, así que se muestran
          los dos bloques del formulario. Llene el que corresponda.
        </Text>
      )}

      <Grid>
        {/* Bloque gineco-obstétrico: lo que el impreso reserva a las mujeres. */}
        {verGineco && (
          <>
            <Grid.Col span={{ base: 12, md: 4 }}>
              <DatePickerInput
                label="Fecha de última menstruación"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(data.fecha_ultima_menstruacion)}
                onChange={(d) => set({ fecha_ultima_menstruacion: fromDateValueOrNull(d) })}
              />
            </Grid.Col>

            {([
              ['gestas', 'Gestas'],
              ['partos', 'Partos'],
              ['cesareas', 'Cesáreas'],
              ['abortos', 'Abortos'],
            ] as const).map(([campo, etiqueta]) => (
              <Grid.Col key={campo} span={{ base: 6, md: 2 }}>
                <NumberInput
                  label={etiqueta}
                  min={0}
                  {...contained}
                  value={data[campo] ?? undefined}
                  onChange={(v) => set({ [campo]: v !== '' ? Number(v) : null })}
                />
              </Grid.Col>
            ))}
          </>
        )}

        {camposComunes}
      </Grid>
    </Stack>
  )
}
