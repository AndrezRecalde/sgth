'use client'

import { Alert, Group, Switch, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  esActoDeAutoridad, esDelContratoCivil, pideDocumentoDeOrigen, pideDocumentoFirmado, pideInformeUath,
  type MovimientoFormData,
} from '../schemas/movimiento.schema'
import type { ClaseDelCatalogo } from '@/types/api'
import { FirmantesPanel } from './FirmantesPanel'
import { MovimientoDatosJudiciales } from './MovimientoDatosJudiciales'
import { MovimientoActaConcurso } from './MovimientoActaConcurso'
import { MovimientoDatosDelPeriodo } from './MovimientoDatosDelPeriodo'
import { MovimientoDatosLicenciaConsejo } from './MovimientoDatosLicenciaConsejo'
import { fromDateValue, fromDateValueOrNull, toDateValue } from '@/lib/fecha'

/**
 * Lo que toda acción de personal declara, sea del tipo que sea: por qué, desde
 * cuándo rige, con qué resolución se respalda, si exige ficha de salud
 * ocupacional y si el puesto pide caución.
 *
 * El período aparte —«desde» y «hasta»— solo en la clase que lo pide —hoy las
 * comisiones de servicios, que es lo único que las define: el servidor no se
 * mueve de puesto—.
 */
export function MovimientoDatosDelActo({
  form,
  clase,
  tipoNombramiento,
}: {
  form: UseFormReturn<MovimientoFormData>
  /** La clase elegida: de su ficha del catálogo sale si pide período y su aviso. */
  clase?: ClaseDelCatalogo
  /** El nombramiento vigente: el traslado pide el informe de la UATH según él. */
  tipoNombramiento?: string | null
}) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  const caucionado = useWatch({ control, name: 'caucionado' })
  const nombramiento = useWatch({ control, name: 'tipo_nombramiento_propuesto' })
  const causal = useWatch({ control, name: 'causal' })

  // El ingreso de un ocasional es el registro de su contrato (fase 2.7): el
  // contrato se firma aparte y aquí nadie firma.
  const esRegistroDeContrato = !!nombramiento
    && !!clase?.registro_de_contrato_para.some((n) => n === nombramiento)

  // Al Prefecto y al Viceprefecto no se les emite acción de personal: el acto
  // lleva su documento de origen y no lo firma nadie (fase 4.1).
  const actoDeAutoridad = esActoDeAutoridad(clase, tipoNombramiento)
  const conDocumentoDeOrigen = pideDocumentoDeOrigen(clase, tipoNombramiento)

  // Quien tiene Servicios Profesionales no es servidor (fase 4.2): su contrato,
  // sus adendas y su terminación los firman las partes.
  const contratoCivil = esDelContratoCivil(clase, tipoNombramiento, nombramiento)
  const conDocumentoFirmado = pideDocumentoFirmado(clase, tipoNombramiento, causal)
  const documentoObligatorio = conDocumentoDeOrigen || conDocumentoFirmado

  const muestraFechas = !!clase?.pide_periodo
  const pideInstitucion = !!clase?.pide_institucion_destino

  return (
    <>
      <Textarea
        label="Explicación"
        placeholder="Detalle y justificación de la acción de personal"
        autosize
        minRows={3}
        {...contained}
        {...register('descripcion')}
        error={errors.descripcion?.message}
      />

      <Controller
        name="fecha_efectiva"
        control={control}
        render={({ field }) => (
          <DatePickerInput
            label="Rige a partir de"
            placeholder="Seleccionar fecha"
            valueFormat="DD/MM/YYYY"
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValue(d))}
            error={errors.fecha_efectiva?.message}
            {...contained}
          />
        )}
      />

      {muestraFechas && (
        <>
          {clase?.aviso && (
            <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
              {clase.aviso}
            </Alert>
          )}
          <Group grow>
            <Controller
              name="fecha_inicio"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Desde"
                  valueFormat="DD/MM/YYYY"
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                  error={errors.fecha_inicio?.message}
                  {...contained}
                />
              )}
            />
            <Controller
              name="fecha_fin"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Hasta"
                  valueFormat="DD/MM/YYYY"
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                  error={errors.fecha_fin?.message}
                  {...contained}
                />
              )}
            />
          </Group>
        </>
      )}

      {pideInstitucion && (
        <TextInput
          label="Institución de destino"
          placeholder="La entidad del Estado a la que va el servidor"
          withAsterisk
          error={errors.institucion_destino?.message}
          {...contained}
          {...register('institucion_destino')}
        />
      )}

      {pideInformeUath(clase, tipoNombramiento) && (
        <TextInput
          label="Informe técnico de la UATH N.º"
          placeholder="Ej. INF-UATH-2026-010"
          description="El traslado se hace previo informe de la UATH, donde consta que el servidor cumple el perfil del puesto (LOSEP Arts. 35 y 36)."
          withAsterisk
          error={errors.informe_uath_numero?.message}
          {...contained}
          {...register('informe_uath_numero')}
        />
      )}

      {clase?.pide_datos_judiciales && <MovimientoDatosJudiciales form={form} />}

      {/* La licencia del Consejo: con o sin remuneración y su prórroga (4.1b). */}
      {clase?.pide_datos_de_licencia_del_consejo && <MovimientoDatosLicenciaConsejo form={form} />}

      {/* La autoridad electa empieza con la credencial del CNE (fase 4.1). */}
      {clase?.pide_datos_del_periodo && (
        <>
          {clase.aviso && (
            <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
              {clase.aviso}
            </Alert>
          )}
          <MovimientoDatosDelPeriodo form={form} />
        </>
      )}

      {/* El ascenso se gana en concurso, y entra de prueba por seis meses con
          su puesto reservado (fase 3.3). */}
      {clase?.pide_acta_concurso && (
        <>
          {clase.aviso && (
            <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
              {clase.aviso}
            </Alert>
          )}
          <MovimientoActaConcurso form={form} />
        </>
      )}

      {clase?.admite_para_estudios_o_eventos && (
        <Controller
          name="para_estudios_o_eventos"
          control={control}
          render={({ field }) => (
            <Switch
              label="Para estudios o eventos"
              description="Al volver, el servidor debe servir en la institución un tiempo igual al de la comisión (LOSEP Art. 30)."
              checked={!!field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />
      )}

      {/* En el cese de una autoridad es su documento de origen (fase 4.1), y
          en el contrato civil el documento firmado por las partes (fase 4.2). */}
      <TextInput
        label={conDocumentoDeOrigen
          ? 'Documento de origen N.º'
          : conDocumentoFirmado ? 'Documento firmado N.º' : 'Número de resolución'}
        description={conDocumentoFirmado
          ? (clase?.pide_adenda
            ? 'El número de la adenda que firmaron las partes.'
            : 'El acta de terminación que firmaron las partes, o la partida de defunción.')
          : !conDocumentoDeOrigen
            ? undefined
            : clase?.pide_datos_de_licencia_del_consejo
              ? 'La resolución del Consejo Provincial que la concede.'
              : clase?.causales.length
                ? 'El resultado del CNE, la resolución del Consejo Provincial, la renuncia aceptada o la partida de defunción.'
                : 'La resolución del Consejo Provincial o el oficio que lo origina.'}
        placeholder={documentoObligatorio ? undefined : 'Opcional'}
        withAsterisk={documentoObligatorio}
        error={errors.resolucion_numero?.message}
        {...contained}
        {...register('resolucion_numero')}
      />

      {/* Las autoridades electas no pasan por la ficha médica [TH 5; fase 4.1]. */}
      {!actoDeAutoridad && (
        <Controller
          name="requiere_dictamen_medico"
          control={control}
          render={({ field }) => (
            <Switch
              label="Requiere ficha de salud ocupacional"
              description={contratoCivil
                ? 'Opcional en el contrato civil. Si se marca, no podrá registrarse sin dictamen de aptitud del dispensario.'
                : 'Si se marca, no podrá registrarse sin dictamen de aptitud del dispensario.'}
              checked={!!field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />
      )}

      {esRegistroDeContrato ? (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          El ingreso de un ocasional no lleva acción de personal (LOSEP Art. 18;
          Reglamento Art. 19): es el registro de su contrato, con su número. El
          contrato se firma aparte, así que aquí no hay firmantes ni documento.
        </Alert>
      ) : actoDeAutoridad ? (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          A una autoridad electa no se le emite acción de personal: ninguno de sus
          actos nace de una autoridad nominadora. Se registra con su documento de
          origen y su propia numeración (AU), sin firmantes.
        </Alert>
      ) : contratoCivil ? (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Quien tiene Servicios Profesionales no es servidor ni tiene relación de
          dependencia (Reglamento a la LOSEP, Art. 148): no se le emite acción de
          personal. El contrato, sus adendas y su terminación los firman las
          partes; aquí se registran, con el número del contrato o el correlativo
          CC, y sin firmantes.
        </Alert>
      ) : (
        <>
          <SectionHeading title="Firmarán este documento" mt={4} mb={4} />

          <Text size="xs" c="dimmed">
            Se toman del organigrama y quedan sellados al suscribir la acción.
          </Text>
          <FirmantesPanel compacto />
        </>
      )}

      <SectionHeading title="Caución" mt={4} mb={4} />

      <Controller
        name="caucionado"
        control={control}
        render={({ field }) => (
          <Switch
            label="El puesto exige caución"
            checked={!!field.value}
            onChange={(e) => field.onChange(e.currentTarget.checked)}
          />
        )}
      />

      {caucionado && (
        <Group grow>
          <TextInput
            label="Caución registrada con No."
            {...contained}
            {...register('caucion_numero')}
            error={errors.caucion_numero?.message}
          />
          <Controller
            name="caucion_fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                valueFormat="DD/MM/YYYY"
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                error={errors.caucion_fecha?.message}
                {...contained}
              />
            )}
          />
        </Group>
      )}

      <Textarea
        label="Observación"
        placeholder="Opcional"
        autosize
        minRows={2}
        error={errors.observacion?.message}
        {...contained}
        {...register('observacion')}
      />
    </>
  )
}
