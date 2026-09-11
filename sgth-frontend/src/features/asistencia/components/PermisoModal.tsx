"use client";

import { Alert, Button, Group, Modal, Stack, Stepper, Text } from "@mantine/core";
import { IconInfoCircle } from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { usePermisoForm } from "../hooks/usePermisoForm";
import { PermisoDatosCampos } from "./PermisoDatosCampos";
import { PermisoSolicitanteCampos } from "./PermisoSolicitanteCampos";
import { RegistroConfirmado } from "./RegistroConfirmado";

interface Props {
  opened: boolean;
  onClose: () => void;
}

/**
 * Registrar un permiso de ausencia, en dos pasos: los datos y la confirmación
 * con el folio.
 *
 * Tenía 531 líneas. El estado y el envío viven en `usePermisoForm`; los
 * campos, en `PermisoSolicitanteCampos` y `PermisoDatosCampos`; y la
 * confirmación, en `RegistroConfirmado`, que comparte con vacaciones.
 */
export function PermisoModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const registro = usePermisoForm(onClose);
  const { isSubmitting } = registro.form.formState;

  return (
    <Modal
      closeOnClickOutside={false}
      opened={opened}
      onClose={registro.cerrar}
      title="Registrar permiso de ausencia"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <Stepper active={registro.paso} mb="lg" size="sm">
        <Stepper.Step label="Datos del permiso" />
        <Stepper.Step label="Confirmación" />
      </Stepper>

      {registro.paso === 0 && !registro.puedeRegistrar && (
        <Alert icon={<IconInfoCircle size={16} />} color="orange" variant="light">
          <Text size="sm">
            Su usuario no está vinculado a un servidor con unidad asignada, así
            que no hay a nombre de quién registrar el permiso. Pídale a Talento
            Humano que lo registre o que complete la vinculación.
          </Text>
        </Alert>
      )}

      {registro.paso === 0 && registro.puedeRegistrar && (
        <form onSubmit={registro.enviar} noValidate>
          <Stack gap="sm">
            <PermisoSolicitanteCampos
              form={registro.form}
              emiteATodos={registro.emiteATodos}
              nombrePropio={registro.nombrePropio}
              unidadSelId={registro.unidadSelId}
              onUnidad={registro.setUnidadSelId}
            />

            <PermisoDatosCampos form={registro.form} />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={registro.cerrar}>
                Cancelar
              </Button>
              <Button type="submit" color="emerald" variant="light" loading={isSubmitting}>
                Crear permiso
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      {registro.paso === 1 && registro.permisoCreado && (
        <RegistroConfirmado
          titulo="Permiso registrado correctamente"
          folio={registro.permisoCreado.folio}
          pregunta="¿Desea exportar el permiso en PDF para firma y archivo físico?"
          exportando={registro.exportando}
          onExportar={registro.exportarCreado}
          onCerrar={registro.cerrar}
        />
      )}
    </Modal>
  );
}
