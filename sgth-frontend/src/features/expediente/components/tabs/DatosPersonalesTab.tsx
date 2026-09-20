'use client'

import { Group, Stack, Text } from '@mantine/core'
import { DetailList, SectionCard, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ServidorConRelaciones } from '@/types/api'

const GENERO_LABELS: Record<string, string> = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  otro: 'Otro',
}

const ESTADO_CIVIL_LABELS: Record<string, string> = {
  soltero: 'Soltero/a',
  casado: 'Casado/a',
  union_libre: 'Unión libre',
  divorciado: 'Divorciado/a',
  viudo: 'Viudo/a',
}

interface Props {
  servidor: ServidorConRelaciones
}

export function DatosPersonalesTab({ servidor }: Props) {
  const nombreCompleto = [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ].filter(Boolean).join(' ')

  const anios = servidor.anios_servicio

  const identificacion = [
    { label: 'Nombre completo', value: nombreCompleto, ancho: true },
    { label: 'Cédula de identidad', value: servidor.cedula },
    {
      label: 'Años de servicio',
      value: anios != null ? `${anios} ${anios === 1 ? 'año' : 'años'}` : null,
    },
    { label: 'Fecha de nacimiento', value: formatFecha(servidor.fecha_nacimiento) },
    { label: 'Género', value: GENERO_LABELS[servidor.genero ?? ''] ?? servidor.genero },
    {
      label: 'Estado civil',
      value: ESTADO_CIVIL_LABELS[servidor.estado_civil ?? ''] ?? servidor.estado_civil,
    },
    { label: 'Tipo de sangre', value: servidor.tipo_sangre },
    { label: 'Papeleta de votación', value: servidor.numero_papeleta_votacion },
    ...(servidor.es_extranjero
      ? [
          { label: 'Nacionalidad', value: servidor.nacionalidad },
          { label: 'País de origen', value: servidor.pais_origen },
          { label: 'Pasaporte', value: servidor.pasaporte_numero },
        ]
      : []),
  ]

  const contacto = [
    { label: 'Teléfono celular', value: servidor.telefono_celular },
    { label: 'Teléfono convencional', value: servidor.telefono_convencional },
    { label: 'Correo personal', value: servidor.correo_personal },
    { label: 'Dirección domiciliaria', value: servidor.direccion_domicilio, ancho: true },
  ]

  const sinCondiciones =
    !servidor.tiene_discapacidad && !servidor.tiene_enfermedad_catastrofica

  return (
    <Stack gap="lg">
      <SectionCard title="Identificación">
        <DetailList items={identificacion} />
      </SectionCard>

      <SectionCard title="Contacto">
        <DetailList items={contacto} />
      </SectionCard>

      <SectionCard
        title="Condición de salud"
        description="El detalle de cada caso vive en la pestaña Condición."
      >
        {sinCondiciones ? (
          <Text size="sm" c="dimmed">Sin condiciones registradas</Text>
        ) : (
          <Group gap="xs">
            {servidor.tiene_discapacidad && (
              <StatusBadge>Tiene discapacidad</StatusBadge>
            )}
            {servidor.tiene_enfermedad_catastrofica && (
              <StatusBadge>Enfermedad catastrófica</StatusBadge>
            )}
          </Group>
        )}
      </SectionCard>
    </Stack>
  )
}
