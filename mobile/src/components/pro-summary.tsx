import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, Text, View } from 'react-native';

import { Avatar } from '@/components/ui/avatar';
import { RatingSummary } from '@/components/ui/stars';
import { Colors, Spacing } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockPro } from '@/mocks/client-flow';

type Props = {
  /** Titre principal ; par défaut le nom du professionnel. */
  title?: string;
  subtitle?: string;
  size?: number;
  chevron?: boolean;
};

/** Identité du professionnel : avatar vérifié, métier, note issue d'interventions terminées (§5.5). */
export function ProSummary({ title, subtitle, size = 60, chevron }: Props) {
  const { t, rtl } = useI18n();

  return (
    <View style={styles.row}>
      <Avatar name={mockPro.name} size={size} verified={mockPro.verified} />
      <View style={styles.body}>
        <View style={styles.nameRow}>
          <Text style={styles.name}>{title ?? mockPro.name}</Text>
          {!title && mockPro.verified ? <Ionicons name="checkmark-circle" size={18} color={Colors.primary} /> : null}
        </View>
        <Text style={styles.subtitle}>{subtitle ?? t.proposal.verifiedPlumber}</Text>
        <RatingSummary rating={mockPro.rating} count={mockPro.reviews} reviewsLabel={t.proposal.reviews} />
      </View>
      {chevron ? <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={20} color={Colors.textSecondary} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  body: { flex: 1, gap: 3 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  name: { fontSize: 18, fontWeight: '700', color: Colors.text },
  subtitle: { fontSize: 14, color: Colors.textSecondary },
});
