'use client'

import { Group, Stack, Text } from '@mantine/core'
import { DetailList, SectionCard, StatusBadge } from '@/components/ui'
import { edadEnAnios, formatFecha } from '@/lib/fecha'
import type { ServidorConRelaciones } from '@/types/api'
import { ESTADO_CIVIL_LABELS, GENERO_LABELS } from '../../constants/servidor'

interface Props {
  servidor: ServidorConRelaciones
  /**
   * La condición de salud remite a la pestaña Condición del expediente. «Mi
   * perfil» del portal no la tiene, así que la oculta.
   */
  conCondicion?: boolean
}

export function DatosPersonalesTab({ servidor, conCondicion = true }: Props) {
  const nombreCompleto = [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ].filter(Boolean).join(' ')

  const edad = edadEnAnios(servidor.fecha_nacimiento)
  const nacimiento = servidor.fecha_nacimiento
    ? `${formatFecha(servidor.fecha_nacimiento)}${edad != null ? ` (${edad} años)` : ''}`
    : null

  const pasaporte = servidor.pasaporte_numero
    ? [
        servidor.pasaporte_numero,
        servidor.pasaporte_vencimiento && `vence el ${formatFecha(servidor.pasaporte_vencimiento)}`,
      ].filter(Boolean).join(' · ')
    : null

  /*
   * Lo que la ficha guarda, todo. Hasta el 2026-10-19 el lugar de nacimiento
   * —obligatorio en el alta— no salía en ninguna parte, el pasaporte de un
   * ecuatoriano tampoco y el código médico solo en el formulario.
   *
   * Los años de servicio ya no van aquí: no son identidad, y los dan la
   * cabecera del expediente y la situación laboral de «Mi perfil».
   */
  const identificacion = [
    { label: 'Nombre completo', value: nombreCompleto, ancho: true },
    { label: 'Cédula de identidad', value: servidor.cedula },
    { label: 'Fecha de nacimiento', value: nacimiento },
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
        ]
      : [
          {
            label: 'Lugar de nacimiento',
            value: [servidor.canton_nacimiento, servidor.provincia_nacimiento]
              .filter(Boolean).join(', '),
          },
        ]),
    // Un ecuatoriano también puede tenerlo: el formulario lo pide a todos.
    ...(servidor.es_extranjero || servidor.pasaporte_numero
      ? [{ label: 'Pasaporte', value: pasaporte }]
      : []),
    // Solo lo tiene el personal de salud; a los demás no se les pinta vacío.
    ...(servidor.codigo_medico
      ? [{ label: 'Código médico (ACESS)', value: servidor.codigo_medico }]
      : []),
  ]

  const contacto = [
    { label: 'Teléfono celular', value: servidor.telefono_celular },
    { label: 'Teléfono convencional', value: servidor.telefono_convencional },
    { label: 'Correo personal', value: servidor.correo_personal },
    { label: 'Dirección domiciliaria', value: servidor.direccion_domicilio, ancho: true },
    {
      label: 'Contacto de emergencia',
      value: servidor.contacto_emergencia_nombre
        ? [
            servidor.contacto_emergencia_nombre
              + (servidor.contacto_emergencia_parentesco ? ` (${servidor.contacto_emergencia_parentesco})` : ''),
            servidor.contacto_emergencia_telefono,
          ].filter(Boolean).join(' · ')
        : null,
      ancho: true,
    },
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

      {conCondicion && (
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
      )}
    </Stack>
  )
}
