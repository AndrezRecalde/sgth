"use client";

import { useState } from "react";
import {
  Modal,
  Stack,
  Textarea,
  Button,
  Group,
  Text,
  Alert,
} from "@mantine/core";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { IconCheck, IconInfoCircle } from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useEmitirReceta } from "../hooks/useReceta";
import { BuscarMedicinaSelect } from "./BuscarMedicinaSelect";
import { ItemRecetaRow } from "./ItemRecetaRow";
import { recetaSchema, type RecetaFormData } from "../schemas/receta.schema";
import { inventarioMedicinaService } from "../services/inventarioMedicinaService";
import type { AgendaMedica } from "../services/agendaService";
import type { ConsultaMedica } from "../services/consultaMedicaService";

interface Props {
  opened: boolean;
  onClose: () => void;
  turno: AgendaMedica | null;
  consulta: ConsultaMedica | null;
  onEmitida: () => void;
}

export function RecetaModal({
  opened,
  onClose,
  turno,
  consulta,
  onEmitida,
}: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const emitir = useEmitirReceta(consulta?.id);

  const [medicinaMeta, setMedicinaMeta] = useState<Record<number, {
    stock:         number
    concentracion?: string | null
    presentacion?:  string | null
  }>>({});

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RecetaFormData>({
    resolver: zodResolver(recetaSchema),
    defaultValues: {
      indicaciones_generales: "",
      items: [],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "items",
  });

  const handleSeleccionarMedicina = async (id: number, nombre: string) => {
    try {
      const medicina = await inventarioMedicinaService.obtener(id);
      setMedicinaMeta((prev) => ({
        ...prev,
        [id]: {
          stock:         medicina.stock_actual,
          concentracion: medicina.concentracion,
          presentacion:  medicina.presentacion,
        },
      }));
    } catch {
      setMedicinaMeta((prev) => ({
        ...prev,
        [id]: { stock: 0 },
      }));
    }

    append({
      inventario_medicina_id: id,
      nombre_medicina: nombre,
      cantidad_prescrita: 1,
      dosis: "",
      frecuencia: "",
      duracion: "",
      observaciones: "",
    });
  };

  const onSubmit = (values: RecetaFormData) => {
    if (!consulta) return;

    const ahora = new Date();
    const fecha = [
      ahora.getFullYear(),
      String(ahora.getMonth() + 1).padStart(2, "0"),
      String(ahora.getDate()).padStart(2, "0"),
    ].join("-");

    emitir.mutate(
      {
        consulta_medica_id: consulta.id,
        fecha_emision: fecha,
        indicaciones_generales: values.indicaciones_generales || null,
        items: values.items.map((item) => ({
          inventario_medicina_id: item.inventario_medicina_id,
          cantidad_prescrita: item.cantidad_prescrita,
          dosis: item.dosis,
          frecuencia: item.frecuencia,
          duracion: item.duracion,
          observaciones: item.observaciones || null,
        })),
      },
      {
        onSuccess: () => {
          reset();
          setMedicinaMeta({});
          onEmitida();
          onClose();
        },
      },
    );
  };

  const nombrePaciente = turno
    ? turno.servidor_id
      ? `${turno.servidor?.nombre ?? ""} ${turno.servidor?.apellido ?? ""}`
      : `${turno.carga_familiar?.nombres ?? ""} ${turno.carga_familiar?.apellidos ?? ""}`
    : "";

  return (
    <Modal
      opened={opened}
      onClose={() => {
        reset();
        setMedicinaMeta({});
        onClose();
      }}
      title="Emitir receta médica"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <form noValidate onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">
          {turno && (
            <Text size="sm" c="dimmed">
              Paciente:{" "}
              <Text span fw={600} c="inherit">
                {nombrePaciente.trim()}
              </Text>
            </Text>
          )}

          <Textarea
            label="Indicaciones generales (opcional)"
            placeholder="Indicaciones adicionales para el paciente"
            autosize
            minRows={2}
            {...contained}
            {...register("indicaciones_generales")}
          />

          <Stack gap="xs">
            <Text size="sm" fw={500}>
              Medicamentos
            </Text>

            <BuscarMedicinaSelect
              onSeleccionar={handleSeleccionarMedicina}
              onCrearNueva={() => {}}
            />

            {fields.length === 0 ? (
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="gray"
                variant="light"
              >
                <Text size="xs">
                  Busca y agrega al menos un medicamento a la receta.
                </Text>
              </Alert>
            ) : (
              <Stack gap="sm">
                {fields.map((field, i) => {
                  const meta = medicinaMeta[field.inventario_medicina_id]
                  return (
                    <ItemRecetaRow
                      key={field.id}
                      index={i}
                      control={control}
                      nombre={field.nombre_medicina}
                      stock={meta?.stock ?? 0}
                      concentracion={meta?.concentracion}
                      presentacion={meta?.presentacion}
                      onEliminar={() => remove(i)}
                    />
                  )
                })}
              </Stack>
            )}

            {errors.items?.message && (
              <Text size="xs" c="red">
                {errors.items.message}
              </Text>
            )}
          </Stack>

          <Group justify="flex-end" mt="sm">
            <Button
              variant="default"
              onClick={() => {
                reset();
                setMedicinaMeta({});
                onClose();
              }}
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={emitir.isPending}
              disabled={fields.length === 0}
            >
              Emitir receta
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
