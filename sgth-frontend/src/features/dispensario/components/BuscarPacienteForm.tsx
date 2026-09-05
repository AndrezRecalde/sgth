"use client";

import { useState } from "react";
import {
  Stack,
  TextInput,
  Button,
  Alert,
  Text,
  ActionIcon,
  Card,
  ThemeIcon,
  Center,
} from "@mantine/core";
import {
  IconSearch,
  IconX,
  IconInfoCircle,
  IconUserSearch,
} from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useBuscarPaciente } from "../hooks/usePaciente";
import { useCrearHistoriaClinica } from "../hooks/useHistoriaClinica";
import { PacienteCard } from "./PacienteCard";
import type { PacienteEncontrado } from "../services/pacienteService";

interface Props {
  onPacienteListo: (
    paciente: PacienteEncontrado,
    historiaClinicaId: number,
  ) => void;
}

export function BuscarPacienteForm({ onPacienteListo }: Props) {
  const contained = useContainedInput();
  const [cedula, setCedula] = useState("");

  const buscar = useBuscarPaciente();
  const crearHistoria = useCrearHistoriaClinica();

  const handleBuscar = () => {
    if (!cedula.trim()) return;
    buscar.mutate(cedula.trim());
  };

  const handleCrearHistoria = async () => {
    const paciente = buscar.data;
    if (!paciente) return;

    const data = await crearHistoria.mutateAsync(
      paciente.tipo === "servidor"
        ? { servidor_id: paciente.id }
        : { carga_familiar_id: paciente.id },
    );

    onPacienteListo(
      {
        ...paciente,
        tiene_historia_clinica: true,
        historia_clinica_id: data.id,
      },
      data.id,
    );
  };

  const handleContinuar = () => {
    const paciente = buscar.data;
    if (!paciente || !paciente.historia_clinica_id) return;
    onPacienteListo(paciente, paciente.historia_clinica_id);
  };

  return (
    <Card shadow="md" padding="xl" withBorder>
      <Stack gap="lg">
        <Center>
          <Stack gap={4} align="center">
            <ThemeIcon variant="light" size={56}>
              <IconUserSearch size={28} />
            </ThemeIcon>
            <Text fw={600} size="md" mt={4}>
              Buscar paciente
            </Text>
            <Text size="xs" c="dimmed" ta="center" maw={320}>
              Ingresa la cédula del servidor o de un familiar registrado como
              carga familiar
            </Text>
          </Stack>
        </Center>

        <TextInput
          label="Cédula del paciente"
          placeholder="Ej: 0801234567"
          {...contained}
          value={cedula}
          onChange={(e) => setCedula(e.currentTarget.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              handleBuscar();
            }
          }}
          style={{ flex: 1 }}
          rightSection={
            cedula ? (
              <ActionIcon
                size="sm"
                variant="subtle"
                color="gray"
                onClick={() => {
                  setCedula("");
                  buscar.reset();
                }}
              >
                <IconX size={12} />
              </ActionIcon>
            ) : null
          }
        />
        <Button
          size="md"
          leftSection={<IconSearch size={14} />}
          loading={buscar.isPending}
          onClick={handleBuscar}
        >
          Buscar
        </Button>

        {buscar.isError && (
          <Alert
            icon={<IconInfoCircle size={14} />}
            color="red"
            variant="light"
          >
            <Text size="xs">
              No se encontró ningún servidor o familiar registrado con esa
              cédula.
            </Text>
          </Alert>
        )}

        {buscar.data && (
          <PacienteCard
            paciente={buscar.data}
            onCrearHistoria={handleCrearHistoria}
            onContinuar={handleContinuar}
            creandoHistoria={crearHistoria.isPending}
          />
        )}
      </Stack>
    </Card>
  );
}
