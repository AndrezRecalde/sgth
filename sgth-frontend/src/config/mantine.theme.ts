import { createTheme, rem } from '@mantine/core';

export const theme = createTheme({
  primaryColor: 'emerald',
  primaryShade: 6,
  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
  defaultRadius: 'md',
  colors: {
    emerald: [
      '#ECFDF5',
      '#D1FAE5',
      '#A7F3D0',
      '#6EE7B7',
      '#34D399',
      '#10B981',
      '#059669',
      '#047857',
      '#065F46',
      '#064E3B',
    ],
  },
  radius: {
    xs: rem(4),
    sm: rem(6),
    md: rem(8),
    lg: rem(10),
    xl: rem(12),
  },
  shadows: {
    none: 'none',
    sm: '0 1px 3px rgba(0,0,0,0.06)',
  },
  components: {
    Button: {
      defaultProps: { radius: 'md' },
    },
    TextInput: {
      defaultProps: { radius: 'md' },
    },
    Select: {
      defaultProps: { radius: 'md' },
    },
    Card: {
      defaultProps: { radius: 'lg', withBorder: true },
    },
    Modal: {
      defaultProps: { radius: 'xl', centered: true },
    },
    Badge: {
      defaultProps: { radius: 'xl' },
    },
  },
});
