import type { ReactNode } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView, type Edge } from 'react-native-safe-area-context';

import { Colors, Spacing } from '@/constants/theme';

type Props = {
  children: ReactNode;
  /** En-tête fixe (ScreenHeader) au-dessus du contenu défilant. */
  header?: ReactNode;
  /** Actions fixes en bas d'écran (boutons principaux). */
  footer?: ReactNode;
  edges?: Edge[];
};

export function Screen({ children, header, footer, edges = ['top', 'left', 'right'] }: Props) {
  return (
    <SafeAreaView style={styles.safe} edges={edges}>
      {header}
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {children}
      </ScrollView>
      {footer ? <View style={styles.footer}>{footer}</View> : null}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  content: { padding: Spacing.md + 4, gap: Spacing.md, flexGrow: 1 },
  footer: { paddingHorizontal: Spacing.md + 4, paddingTop: Spacing.sm, paddingBottom: Spacing.md, gap: Spacing.xs },
});
