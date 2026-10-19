import { Platform } from 'react-native';

/** Palette « Urgence Tunisie », relevée sur les maquettes 01/02. */
export const Colors = {
  primary: '#14A098',
  primaryDark: '#0E857E',
  primarySoft: '#E3F5F2',
  primaryText: '#FFFFFF',
  navy: '#123B4E',
  navySoft: '#1D4A5F',
  text: '#0F2533',
  textSecondary: '#5F7180',
  textMuted: '#93A1AC',
  background: '#FFFFFF',
  surface: '#F4F8FA',
  border: '#E3EAEE',
  danger: '#D93A4F',
  dangerSoft: '#FDE9EC',
  warning: '#C27A00',
  warningSoft: '#FFF1D6',
  warningBorder: '#F6D58E',
  success: '#18A47C',
  info: '#2F6FD6',
  infoSoft: '#E6F0FB',
  star: '#F5B301',
  map: {
    land: '#EEF2EC',
    landAlt: '#E4EBE2',
    sea: '#BFE3F2',
    road: '#FFFFFF',
    roadMajor: '#F8E7B0',
    label: '#4C5B66',
  },
} as const;

export const Spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
} as const;

export const Radius = {
  sm: 8,
  md: 12,
  lg: 16,
  xl: 22,
  pill: 999,
} as const;

export const Typography = {
  display: { fontSize: 30, lineHeight: 36, fontWeight: '800' as const, color: Colors.text },
  title: { fontSize: 22, lineHeight: 28, fontWeight: '800' as const, color: Colors.text },
  heading: { fontSize: 18, lineHeight: 24, fontWeight: '700' as const, color: Colors.text },
  body: { fontSize: 15, lineHeight: 21, fontWeight: '400' as const, color: Colors.text },
  bodyStrong: { fontSize: 15, lineHeight: 21, fontWeight: '700' as const, color: Colors.text },
  caption: { fontSize: 13, lineHeight: 18, fontWeight: '400' as const, color: Colors.textSecondary },
  small: { fontSize: 11, lineHeight: 15, fontWeight: '500' as const, color: Colors.textSecondary },
};

export const Shadow = Platform.select({
  web: { boxShadow: '0 6px 18px rgba(15, 37, 51, 0.06)' },
  default: {
    shadowColor: '#0F2533',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 2,
  },
});
