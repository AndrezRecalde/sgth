'use client'

import { Alert, Button, Grid, Input, NumberInput, SegmentedControl, Select, Stack, Switch, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconEye, IconInfoCircle } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue, fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useContratoColectivo, usePrepararIncremento, usePrevisualizarIncremento } from '../hooks/useContratoColectivo'
import { incrementoEnLoteSchema, type IncrementoEnLoteFormData } from '../schemas/contratoColectivo.schema'
import type { FilaDelIncremento } from '../services/contratoColectivoService'
import { dinero } from '../utils/dinero'

const MODOS = [
  { value: 'porcentaje', label: 'Porcentaje' },
  { value: 'monto', label: 'Monto adicional' },
]

const CAMPOS = [
  'modo', 'valor', 'unidad_administrativa_id', 'fecha_efectiva', 'descripcion', 'documento_origen', 'dictamen_presupuestario_ref',
] as const

/**
 * El incremento de remuneración de los obreros en lote (fase 4.6): el de la
 * tabla sectorial del Ministerio del Trabajo o el que pacta el contrato
 * colectivo. Prepara un borrador por obrero, cada uno con su hoja de registro
 * interno; la vista previa dice a quiénes alcanza y a quiénes omite.
 */
export function IncrementoEnLoteModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  return opened ? <Formulario onClose={onClose} /> : null
}

function Formulario({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const pactado = useContratoColectivo().data?.vigente?.incremento_pactado_porcentaje ?? null
  const previsualizar = usePrevisualizarIncremento()
  const preparar = usePrepararIncremento()

  const {
    control, register, handleSubmit, setValue, setError, getValues, formState: { errors },
  } = useForm<IncrementoEnLoteFormData>({
    resolver: zodResolver(incrementoEnLoteSchema),
    defaultValues: {
      modo: 'porcentaje',
      valor: undefined,
      unidad_administrativa_id: null,
      por_contrato_colectivo: false,
      fecha_efectiva: fromDateValue(new Date()),
      descripcion: '',
      documento_origen: '',
      dictamen_presupuestario_ref: '',
    },
  })

  const [modo, valor, unidad, porContrato] = useWatch({
    control, name: ['modo', 'valor', 'unidad_administrativa_id', 'por_contrato_colectivo'],
  })

  // La vista previa vale para lo que se previsualizó: si cambian el modo, el
  // valor o la unidad, deja de mostrarse.
  const clave = JSON.stringify([modo, valor, unidad ?? null])
  const vista = previsualizar.data && previsualizar.variables
    && JSON.stringify([previsualizar.variables.modo, previsualizar.variables.valor, previsualizar.variables.unidad_administrativa_id ?? null]) === clave
    ? previsualizar.data
    : null

  // El del contrato colectivo: su porcentaje, sin editarlo.
  const usarElPactado = (si: boolean) => {
    setValue('por_contrato_colectivo', si)
    if (si && pactado) {
      setValue('modo', 'porcentaje')
      setValue('valor', Number(pactado))
    }
  }

  const verVistaPrevia = () => {
    const v = getValues()
    if (!(v.valor > 0)) {
      setError('valor', { type: 'manual', message: 'Indique el valor' })
      return
    }
    previsualizar.mutate({ modo: v.modo, valor: v.valor, unidad_administrativa_id: v.unidad_administrativa_id ?? null })
  }

  const enviar = async (v: IncrementoEnLoteFormData) => {
    try {
      await preparar.mutateAsync({
        ...v,
        unidad_administrativa_id: v.unidad_administrativa_id ?? null,
        descripcion: v.descripcion.trim(),
        documento_origen: v.documento_origen.trim(),
        dictamen_presupuestario_ref: v.dictamen_presupuestario_ref.trim(),
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of CAMPOS) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
    }
  }

  const columnas: DataTableColumn<FilaDelIncremento>[] = [
    {
      accessor: 'nombre',
      title: 'Obrero',
      render: (f) => (
        <div>
          <Text size="sm">{f.nombre}</Text>
          <Text size="xs" c="dimmed">{[f.puesto, f.unidad].filter(Boolean).join(' · ') || '—'}</Text>
        </div>
      ),
    },
    { accessor: 'remuneracion_actual', title: 'Actual', width: 110, render: (f) => dinero(f.remuneracion_actual) },
    { accessor: 'remuneracion_nueva', title: 'Nueva', width: 110, render: (f) => dinero(f.remuneracion_nueva) },
  ]

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Incremento en lote"
      onSubmit={handleSubmit(enviar)}
      submitLabel={vista ? `Preparar ${vista.incluidos.length} borrador(es)` : 'Preparar borradores'}
      submitting={preparar.isPending}
      size="xl"
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Prepara un borrador por obrero con contrato vigente, cada uno con su hoja de
          registro interno. El acuerdo de la tabla sectorial o la cláusula del contrato
          colectivo hace las veces de la adenda, y el dictamen presupuestario es uno para
          todos. Después se suscriben y registran desde el lote.
        </Alert>

        <SectionHeading title="Cuánto y a quiénes" mb={0} />
        <Grid>
          {pactado && (
            <Grid.Col span={12}>
              <Controller
                name="por_contrato_colectivo"
                control={control}
                render={({ field }) => (
                  <Switch
                    label={`El pactado en el contrato colectivo vigente: ${Number(pactado)} %`}
                    checked={field.value}
                    onChange={(e) => usarElPactado(e.currentTarget.checked)}
                  />
                )}
              />
            </Grid.Col>
          )}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            {/* Sin el patrón contained: un SegmentedControl no tiene casilla
                donde ponerle la etiqueta. */}
            <Input.Wrapper label="Cómo se calcula">
              <Controller
                name="modo"
                control={control}
                render={({ field }) => (
                  <SegmentedControl
                    data={MODOS}
                    value={field.value}
                    onChange={(v) => field.onChange(v === 'monto' ? 'monto' : 'porcentaje')}
                    disabled={porContrato}
                    fullWidth
                    mt={4}
                  />
                )}
              />
            </Input.Wrapper>
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="valor"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label={modo === 'porcentaje' ? 'Porcentaje' : 'Monto adicional'}
                  withAsterisk
                  min={0}
                  decimalScale={2}
                  hideControls
                  suffix={modo === 'porcentaje' ? ' %' : undefined}
                  prefix={modo === 'monto' ? '$ ' : undefined}
                  disabled={porContrato}
                  error={errors.valor?.message}
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="unidad_administrativa_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Unidad administrativa"
                  placeholder="Todos los obreros"
                  data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
                  searchable
                  clearable
                  error={errors.unidad_administrativa_id?.message}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="fecha_efectiva"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Rige desde"
                  withAsterisk
                  valueFormat="DD/MM/YYYY"
                  popoverProps={{ withinPortal: true, zIndex: 1100 }}
                  error={errors.fecha_efectiva?.message}
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        <Button
          variant="light"
          leftSection={<IconEye size={16} />}
          onClick={verVistaPrevia}
          loading={previsualizar.isPending}
          style={{ alignSelf: 'flex-start' }}
        >
          Ver a quiénes alcanza
        </Button>

        {vista && (
          <Stack gap="xs">
            <Text size="sm">
              <b>{vista.incluidos.length}</b> obrero(s) reciben el incremento
              {vista.omitidos.length > 0 && <>; <b>{vista.omitidos.length}</b> se omiten</>}.
            </Text>
            {vista.incluidos.length > 0 && (
              <SgthTable records={vista.incluidos} columns={columnas} idAccessor="servidor_id" minHeight={80} maxHeight={260} />
            )}
            {vista.omitidos.length > 0 && (
              <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />} title="Se omiten">
                <Stack gap={2}>
                  {vista.omitidos.map((f) => (
                    <Text key={f.servidor_id} size="sm">{f.nombre}: {f.motivo}</Text>
                  ))}
                </Stack>
              </Alert>
            )}
          </Stack>
        )}

        <SectionHeading title="El respaldo" mb={0} />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Documento de origen"
              placeholder="Ej. el acuerdo de la tabla sectorial, o la cláusula del contrato colectivo"
              withAsterisk
              error={errors.documento_origen?.message}
              {...contained}
              {...register('documento_origen')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Dictamen presupuestario"
              withAsterisk
              error={errors.dictamen_presupuestario_ref?.message}
              {...contained}
              {...register('dictamen_presupuestario_ref')}
            />
          </Grid.Col>
        </Grid>
        <Textarea
          label="Explicación"
          placeholder="Va en la hoja de registro interno de cada obrero"
          withAsterisk
          autosize
          minRows={2}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
