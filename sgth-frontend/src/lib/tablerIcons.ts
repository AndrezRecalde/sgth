import * as TablerIcons from "@tabler/icons-react";
import type { Icon } from "@tabler/icons-react";
import { IconPoint } from "@tabler/icons-react";
import React from "react";

type IconName = keyof typeof TablerIcons;

const ICON_SIZE = 22;

export function getNavIcon(name: string): React.ReactElement {
  const IconComponent = TablerIcons[name as IconName] as Icon | undefined;
  if (!IconComponent) {
    return React.createElement(IconPoint, { size: ICON_SIZE, stroke: "2" }); // Icono de error genérico
  }
  return React.createElement(IconComponent, { size: ICON_SIZE, stroke: "2" });
}
