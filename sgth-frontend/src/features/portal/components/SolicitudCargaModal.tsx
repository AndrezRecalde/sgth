'use client'

import { Alert, Stack, Text, Textarea } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import type { MiCargaFamiliar } from '@/types/api'
import { AVISO_CONDICIONES_DE_SALUD } from '../constants/misCargas'
import { useMisCargasMutations } from '../hooks/useMisCargas'
import { solicitudCargaSchema, type SolicitudCargaFormData } from '../schemas/solicitudCarga.schema'
import { CamposDelFamiliar } from './CamposDelFamiliar'

interface Props {
  opened:  boolean
  onClose: () => void
  /** La carga que se corrige; sin ella, se registra un familiar nuevo. */
  carga?:  MiCargaFamiliar | null
}

const CAMPOS = ['cedula', 'nombres', 'apellidos', 'parentesco', 'genero', 'fecha_nacimiento', 'motivo', 'documento']

/** El sexo llega como texto del API: solo valen los dos del formulario. */
const sexoDe = (genero: string | null): SolicitudCargaFormData['genero'] | undefined =>
  genero === 'masculino' || genero === 'femenino' ? genero : undefined

/** Parentesco y sexo se omiten en el registro: los elige quien registra (regla 07). */
const valoresDe = (carga?: MiCargaFamiliar | null): DefaultValues<SolicitudCargaFormData> => (carga
  ? {
      cedula:           carga.cedula ?? '',
      nombres:          carga.nombres,
      apellidos:        carga.apellidos,
      parentesco:       carga.parentesco,
      genero:           sexoDe(carga.genero),
      fecha_nacimiento: carga.fecha_nacimiento,
      motivo:           '',
      documento:        null,
    }
  : { cedula: '', nombres: '', apellidos: '', fecha_nacimiento: '', motivo: '', documento: null })

/**
 * Registrar a un familiar o pedir que se corrijan sus datos, desde «Mi
 * perfil» (docs/diseno-cargas-familiares-portal.md, 5). Lo que se manda es una
 * solicitud: no cuenta hasta que Talento Humano la valida. El padre lo monta
 * con `key` por carga, así que cada apertura parte de los datos vigentes.
 */
export function SolicitudCargaModal({ opened, onClose, carga }: Props) {
  const contained = useContainedInput()
  const { presentar } = useMisCargasMutations()
  const form = useForm<SolicitudCargaFormData>({
    resolver: zodResolver(solicitudCargaSchema(carga)),
    defaultValues: valoresDe(carga),
  })

  const cerrar = () => {
    form.reset(valoresDe(carga))
    onClose()
  }

  const enviar = form.handleSubmit((v) => {
    presentar.mutateAsync(carga
      ? {
          tipo: 'correccion', carga_familiar_id: carga.id,
          nombres: v.nombres, apellidos: v.apellidos, parentesco: v.parentesco, genero: v.genero,
          fecha_nacimiento: v.fecha_nacimiento, motivo: v.motivo || null, documento: v.documento,
        }
      : {
          tipo: 'alta', cedula: v.cedula,
          nombres: v.nombres, apellidos: v.apellidos, parentesco: v.parentesco, genero: v.genero,
          fecha_nacimiento: v.fecha_nacimiento, documento: v.documento,
        })
      .then(cerrar)
      .catch((e) => erroresAlFormulario(e, form.setError, CAMPOS, 'No se pudo enviar la solicitud a Talento Humano'))
  })

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={carga ? 'Pedir corrección de datos' : 'Registrar familiar'}
      size="md"
      onSubmit={enviar}
      submitLabel={carga ? 'Enviar corrección' : 'Enviar a Talento Humano'}
      submitting={presentar.isPending}
    >
      <Stack gap="sm">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          <Text size="sm">
            {carga
              ? 'Talento Humano revisará la corrección. Mientras tanto, los datos de su familiar siguen como están.'
              : 'Talento Humano revisará la solicitud. Hasta que la valide, su familiar no puede atenderse en el Dispensario.'}
          </Text>
          {!carga && <Text size="sm" mt="xs">{AVISO_CONDICIONES_DE_SALUD}</Text>}
        </Alert>

        <CamposDelFamiliar form={form} original={carga} />

        {carga && (
          <Textarea label="Qué corrige (opcional)" placeholder="Ej: el segundo apellido estaba mal escrito"
            rows={2} {...contained} {...form.register('motivo')}
            error={form.formState.errors.motivo?.message} />
        )}
      </Stack>
    </FormModal>
  )
}
