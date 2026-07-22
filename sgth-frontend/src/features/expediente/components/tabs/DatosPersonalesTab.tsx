"use client";

import { Stack, Group, Text, Badge, Divider } from "@mantine/core";
import type { ServidorConRelaciones } from "@/types/api";

const GENERO_LABELS: Record<string, string> = {
  masculino: "Masculino",
  femenino: "Femenino",
  otro: "Otro",
};

const ESTADO_CIVIL_LABELS: Record<string, string> = {
  soltero: "Soltero/a",
  casado: "Casado/a",
  union_libre: "Unión libre",
  divorciado: "Divorciado/a",
  viudo: "Viudo/a",
};

/* const REGIMEN_LABELS: Record<string, string> = {
  losep:          'LOSEP',
  codigo_trabajo: 'Código del Trabajo',
} */

interface Props {
  servidor: ServidorConRelaciones;
}

function Campo({ label, value }: { label: string; value?: string | null }) {
  return (
    <Group justify="space-between" wrap="nowrap">
      <Text size="sm" c="dimmed" style={{ minWidth: 180 }}>
        {label}
      </Text>
      <Text size="sm" fw={500} ta="right">
        {value ?? "—"}
      </Text>
    </Group>
  );
}

export function DatosPersonalesTab({ servidor }: Props) {
  const nombreCompleto = [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <Stack gap="xs">
      <Divider label="Identificación" labelPosition="left" my="xs" />
      <Campo label="Nombre completo" value={nombreCompleto} />
      <Campo label="Cédula de identidad" value={servidor.cedula} />
      <Campo
        label="Años de servicio"
        value={
          servidor.anios_servicio != null
            ? `${servidor.anios_servicio} ${servidor.anios_servicio === 1 ? "año" : "años"}`
            : "—"
        }
      />
      <Campo
        label="Fecha de nacimiento"
        value={
          servidor.fecha_nacimiento
            ? new Date(servidor.fecha_nacimiento).toLocaleDateString("es-EC", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                timeZone: "UTC",
              })
            : "—"
        }
      />
      <Campo
        label="Género"
        value={GENERO_LABELS[servidor.genero ?? ""] ?? servidor.genero}
      />
      <Campo
        label="Estado civil"
        value={
          ESTADO_CIVIL_LABELS[servidor.estado_civil ?? ""] ??
          servidor.estado_civil
        }
      />
      <Campo label="Tipo de sangre" value={servidor.tipo_sangre} />
      <Campo
        label="Papeleta de votación"
        value={servidor.numero_papeleta_votacion}
      />

      {servidor.es_extranjero && (
        <>
          <Campo label="Nacionalidad" value={servidor.nacionalidad} />
          <Campo label="País de origen" value={servidor.pais_origen} />
          <Campo label="Pasaporte" value={servidor.pasaporte_numero} />
        </>
      )}

      <Divider label="Contacto" labelPosition="left" my="xs" />
      <Campo label="Teléfono celular" value={servidor.telefono_celular} />
      <Campo
        label="Teléfono convencional"
        value={servidor.telefono_convencional}
      />
      <Campo label="Correo personal" value={servidor.correo_personal} />

      <Divider label="Domicilio" labelPosition="left" my="xs" />
      <Campo label="Dirección" value={servidor.direccion_domicilio} />

      <Divider label="Condición" labelPosition="left" my="xs" />
      <Group>
        {servidor.tiene_discapacidad && (
          <Badge color="orange" variant="light" size="sm">
            Tiene discapacidad
          </Badge>
        )}
        {servidor.tiene_enfermedad_catastrofica && (
          <Badge color="red" variant="light" size="sm">
            Enfermedad catastrófica
          </Badge>
        )}
        {!servidor.tiene_discapacidad &&
          !servidor.tiene_enfermedad_catastrofica && (
            <Text size="sm" c="dimmed">
              Sin condiciones registradas
            </Text>
          )}
      </Group>
    </Stack>
  );
}
