import Ionicons from '@expo/vector-icons/Ionicons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Colors, Spacing } from '@/constants/theme';

/** Note compacte « ★ 4,9 · 48 avis ». */
export function RatingSummary({ rating, count, reviewsLabel }: { rating: number; count: number; reviewsLabel: string }) {
  return (
    <View style={styles.summary}>
      <Ionicons name="star" size={15} color={Colors.star} />
      <Text style={styles.summaryText}>
        <Text style={styles.summaryStrong}>{rating.toFixed(1).replace('.', ',')}</Text> · {count} {reviewsLabel}
      </Text>
    </View>
  );
}

/** Saisie d'une note de 1 à 5. */
export function StarInput({ value, onChange }: { value: number; onChange: (value: number) => void }) {
  return (
    <View style={styles.input}>
      {[1, 2, 3, 4, 5].map((n) => (
        <Pressable key={n} accessibilityRole="button" accessibilityLabel={`${n} / 5`} hitSlop={6} onPress={() => onChange(n)}>
          <Ionicons name={n <= value ? 'star' : 'star-outline'} size={42} color={n <= value ? Colors.star : Colors.textMuted} />
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  summary: { flexDirection: 'row', alignItems: 'center', gap: Spacing.xs },
  summaryText: { fontSize: 13, color: Colors.textSecondary },
  summaryStrong: { color: Colors.text, fontWeight: '700' },
  input: { flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: Spacing.sm },
});
