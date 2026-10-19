import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, Text, View } from 'react-native';

import type { PublicProfessional } from '@/api/types';
import { Avatar } from '@/components/ui/avatar';
import { RatingSummary } from '@/components/ui/stars';
import { Colors, Spacing } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';

type Props = {
  professional: PublicProfessional;
  /** Titre principal ; par défaut le nom du professionnel. */
  title?: string;
  subtitle?: string;
  size?: number;
};

/** Identité du professionnel : avatar vérifié, note issue d'interventions terminées (§5.5). */
export function ProSummary({ professional, title, subtitle, size = 60 }: Props) {
  const { t } = useI18n();

  return (
    <View style={styles.row}>
      <Avatar name={professional.displayName} size={size} verified />
      <View style={styles.body}>
        <View style={styles.nameRow}>
          <Text style={styles.name}>{title ?? professional.displayName}</Text>
          {!title ? <Ionicons name="checkmark-circle" size={18} color={Colors.primary} /> : null}
        </View>
        <Text style={styles.subtitle}>{subtitle ?? t.proposal.verifiedPro}</Text>
        {professional.ratingAverage !== null && professional.ratingCount > 0 ? (
          <RatingSummary rating={professional.ratingAverage} count={professional.ratingCount} reviewsLabel={t.proposal.reviews} />
        ) : (
          <Text style={styles.subtitle}>{t.proposal.noReviews}</Text>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  body: { flex: 1, gap: 3 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  name: { fontSize: 18, fontWeight: '700', color: Colors.text, flexShrink: 1 },
  subtitle: { fontSize: 14, color: Colors.textSecondary },
});
