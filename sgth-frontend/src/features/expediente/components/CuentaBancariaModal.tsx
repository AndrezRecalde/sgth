'use client'

import { Modal, Button, Group, Select, TextInput, Grid } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useEntidadesFinancieras } from '../hooks/useEntidadesFinancieras'
import { useCuentaBancariaMutations } from '../hooks/useCuentaBancariaMutations'
import {
  cuentaBancariaSchema,
  type CuentaBancariaFormData,
} from '../schemas/cuentaBancaria.schema'
import type { EntidadFinanciera } from '@/types/api'

const TIPO_CUENTA_OPTIONS = [
  { value: 'ahorros',   label: 'Ahorros' },
  { value: 'corriente', label: 'Corriente' },
]

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
}

export function CuentaBancariaModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear } = useCuentaBancariaMutations(servidorId)
  const { data: entidades = [] } = useEntidadesFinancieras()

  const form = useForm<CuentaBancariaFormData>({
    initialValues: {
      entidad_financiera_id: '' as unknown as number,
      numero_cuenta:         '',
      tipo_cuenta:           'ahorros',
    },
    validate: zodResolver(cuentaBancariaSchema),
  })

  const entidadOptions = (entidades as EntidadFinanciera[]).map(e => ({
    value: String(e.id),
    label: (e as EntidadFinanciera & { nombre?: string }).nombre
      ?? `Entidad ${e.id}`,
  }))

  const handleSubmit = (values: CuentaBancariaFormData) => {
    crear.mutateAsync(values)
      .then(() => { form.reset(); onClose() })
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Nueva cuenta bancaria"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Grid>
          <Grid.Col span={12}>
            <Select
              label="Entidad financiera"
              data={entidadOptions}
              searchable
              {...contained}
              value={form.values.entidad_financiera_id
                ? String(form.values.entidad_financiera_id) : ''}
              onChange={(v) =>
                form.setFieldValue('entidad_financiera_id',
                  v ? Number(v) : '' as unknown as number)}
              error={form.errors.entidad_financiera_id}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 8 }}>
            <TextInput
              label="Número de cuenta"
              placeholder="Número de cuenta bancaria"
              {...contained}
              {...form.getInputProps('numero_cuenta')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Select
              label="Tipo de cuenta"
              data={TIPO_CUENTA_OPTIONS}
              {...contained}
              value={form.values.tipo_cuenta}
              onChange={(v) =>
                form.setFieldValue('tipo_cuenta',
                  (v ?? 'ahorros') as 'ahorros' | 'corriente')}
            />
          </Grid.Col>
        </Grid>
        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={onClose}>Cancelar</Button>
          <Button type="submit" loading={crear.isPending} color="emerald">
            Registrar cuenta
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
