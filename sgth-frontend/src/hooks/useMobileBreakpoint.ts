import { useMediaQuery } from '@mantine/hooks'

export function useMobileBreakpoint() {
  const isMobile = useMediaQuery('(max-width: 767px)')
  const isTablet = useMediaQuery('(max-width: 991px)')
  const isDesktop = useMediaQuery('(min-width: 992px)')

  return {
    isMobile,
    isTablet,
    isDesktop,
  }
}
