"use client";

import { ActionIcon, Menu } from "@mantine/core";
import { IconDots } from "@tabler/icons-react";

export type TableAction = {
  label: string;
  icon: React.ReactNode;
  color?: string;
  onClick: () => void;
  hidden?: boolean;
  disabled?: boolean;
};

interface TableActionsProps {
  actions: TableAction[];
}

export function TableActions({ actions }: TableActionsProps) {
  const visibles = actions.filter((a) => !a.hidden);
  if (!visibles.length) return null;

  return (
    <Menu shadow="md" width={180} position="bottom-end" withinPortal>
      <Menu.Target>
        <ActionIcon variant="subtle" color="gray" aria-label="Acciones">
          <IconDots size={16} />
        </ActionIcon>
      </Menu.Target>
      <Menu.Dropdown>
        {visibles.map((action, i) => (
          <Menu.Item
            key={i}
            leftSection={action.icon}
            color={action.disabled ? "gray" : action.color}
            disabled={action.disabled}
            onClick={action.onClick}
          >
            {action.label}
          </Menu.Item>
        ))}
      </Menu.Dropdown>
    </Menu>
  );
}
