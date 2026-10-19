import type { ReactNode } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Colors, Spacing } from '@/constants/theme';

type Props = {
  map: ReactNode;
  header: ReactNode;
  /** Élément flottant au-dessus de la carte (ex. : heure d'arrivée estimée). */
  floating?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
};

const SHEET_OVERLAP = 28;

/**
 * Carte en haut + feuille blanche arrondie en bas, comme sur « Recherche en cours » et « Suivre l'intervention ».
 * La carte occupe exactement l'espace laissé par la feuille, pour que ses repères restent visibles.
 */
export function MapSheetLayout({ map, header, floating, children, footer }: Props) {
  return (
    <View style={styles.root}>
      <View style={styles.mapArea}>
        {map}
        <SafeAreaView edges={['top', 'left', 'right']} style={styles.headerArea}>
          {header}
          {floating ? <View style={styles.floating}>{floating}</View> : null}
        </SafeAreaView>
      </View>
      <View style={styles.sheet}>
        <View style={styles.handle} />
        <ScrollView contentContainerStyle={styles.content}>{children}</ScrollView>
        {footer ? (
          <SafeAreaView edges={['bottom', 'left', 'right']} style={styles.footer}>
            {footer}
          </SafeAreaView>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: Colors.map.land },
  mapArea: { flex: 1, minHeight: 220 },
  headerArea: { position: 'absolute', top: 0, left: 0, right: 0 },
  floating: { alignItems: 'center', marginTop: Spacing.xs },
  sheet: {
    flexShrink: 1,
    maxHeight: '64%',
    marginTop: -SHEET_OVERLAP,
    backgroundColor: Colors.background,
    borderTopLeftRadius: SHEET_OVERLAP,
    borderTopRightRadius: SHEET_OVERLAP,
    paddingTop: Spacing.sm,
  },
  handle: { alignSelf: 'center', width: 44, height: 5, borderRadius: 3, backgroundColor: Colors.border },
  content: { padding: Spacing.md + 4, gap: Spacing.md },
  footer: { paddingHorizontal: Spacing.md + 4, paddingBottom: Spacing.md, gap: Spacing.xs },
});
