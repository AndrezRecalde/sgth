'use client'

import { Modal, Button, Group, Stack, Select,
  TextInput, NumberInput, Grid } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { useContratoMutations } from '../hooks/useContratoMutations'
import { contratoSchema, type ContratoFormData } from '../schemas/contrato.schema'
import type { UnidadConRelaciones, Puesto } from '@/types/api'

const TIPO_OPTIONS = [
  { value: 'nombramiento_permanente',       label: 'Nombramiento permanente' },
  { value: 'nombramiento_provisional',      label: 'Nombramiento provisional' },
  { value: 'servicios_ocasionales',         label: 'Servicios ocasionales' },
  { value: 'libre_nombramiento_remocion',   label: 'Libre nombramiento y remoción' },
  { value: 'codigo_trabajo',                label: 'Código del trabajo' },
  { value: 'servicios_profesionales',       label: 'Servicios profesionales' },
]

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
}

export function ContratoModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear } = useContratoMutations(servidorId)
  const { data: unidades = [] } = useUnidades()
  const { data: puestosData } = usePuestos()

  const form = useForm<ContratoFormData>({
    initialValues: {
      tipo_nombramiento:        '',
      unidad_administrativa_id: '' as unknown as number,
      puesto_id:                '' as unknown as number,
      fecha_ingreso:            '',
      fecha_fin:                null,
      remuneracion:             '' as unknown as number,
    },
    validate: zodResolver(contratoSchema),
  })

  const unidadOptions = (unidades as unknown as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  type PuestoConNombre = Puesto & { nombre?: string }
  const puestoOptions = (puestosData?.data ?? [] as PuestoConNombre[])
    .map((p: PuestoConNombre) => ({
      value: String(p.id),
      label: p.nombre ?? `Puesto ${p.id}`,
    }))

  const handleSubmit = (values: ContratoFormData) => {
    crear.mutateAsync(values)
      .then(() => { form.reset(); onClose() })
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Nuevo contrato"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Grid>
          <Grid.Col span={12}>
            <Select
              label="Tipo de nombramiento"
              data={TIPO_OPTIONS}
              searchable
              {...contained}
              value={form.values.tipo_nombramiento}
              onChange={(v) =>
                form.setFieldValue('tipo_nombramiento', v ?? '')}
              error={form.errors.tipo_nombramiento}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Select
              label="Unidad administrativa"
              data={unidadOptions}
              searchable
              {...contained}
              value={form.values.unidad_administrativa_id
                ? String(form.values.unidad_administrativa_id) : ''}
              onChange={(v) =>
                form.setFieldValue('unidad_administrativa_id',
                  v ? Number(v) : '' as unknown as number)}
              error={form.errors.unidad_administrativa_id}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Select
              label="Puesto"
              data={puestoOptions}
              searchable
              {...contained}
              value={form.values.puesto_id
                ? String(form.values.puesto_id) : ''}
              onChange={(v) =>
                form.setFieldValue('puesto_id',
                  v ? Number(v) : '' as unknown as number)}
              error={form.errors.puesto_id}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Fecha de ingreso"
              placeholder="YYYY-MM-DD"
              {...contained}
              {...form.getInputProps('fecha_ingreso')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Fecha de fin"
              placeholder="YYYY-MM-DD (opcional)"
              {...contained}
              value={form.values.fecha_fin ?? ''}
              onChange={(e) =>
                form.setFieldValue('fecha_fin',
                  e.currentTarget.value || null)}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <NumberInput
              label="Remuneración"
              placeholder="0.00"
              decimalScale={2}
              prefix="$"
              {...contained}
              value={form.values.remuneracion ?? ''}
              onChange={(v) =>
                form.setFieldValue('remuneracion',
                  typeof v === 'number' ? v : '' as unknown as number)}
              error={form.errors.remuneracion}
            />
          </Grid.Col>
        </Grid>
        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={onClose}>Cancelar</Button>
          <Button type="submit" loading={crear.isPending} color="emerald">
            Registrar contrato
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
