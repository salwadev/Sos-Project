import Ionicons from '@expo/vector-icons/Ionicons';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import type { QuoteLine } from '@/api/types';
import { Button } from '@/components/ui/button';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { formatMillimes, parseTNDToMillimes } from '@/utils/format';

type Draft = { key: number; label: string; amount: string };

type Props = {
  /** À partir de la 2e version, le professionnel doit expliquer le changement (§9.2). */
  requiresExplanation: boolean;
  /** Frais de déplacement déjà acceptés, proposés comme première ligne. */
  travelFeeMillimes: number;
  pending: boolean;
  onSubmit: (lines: QuoteLine[], explanation: string) => void;
};

/** Saisie d'un devis ligne par ligne, montants en dinars convertis en millimes pour l'API. */
export function QuoteForm({ requiresExplanation, travelFeeMillimes, pending, onSubmit }: Props) {
  const { t } = useI18n();
  const s = t.proSpace.quote;
  const [lines, setLines] = useState<Draft[]>([
    { key: 1, label: s.travelLine, amount: String(travelFeeMillimes / 1000) },
    { key: 2, label: '', amount: '' },
  ]);
  const [explanation, setExplanation] = useState('');

  const parsed = lines
    .filter((l) => l.label.trim() !== '' || l.amount.trim() !== '')
    .map((l) => ({ label: l.label.trim(), amountMillimes: parseTNDToMillimes(l.amount) }));
  const complete = parsed.filter((l): l is QuoteLine => l.label !== '' && l.amountMillimes !== null);
  const valid =
    complete.length > 0 && complete.length === parsed.length && (!requiresExplanation || explanation.trim().length > 0);
  const total = complete.reduce((sum, l) => sum + l.amountMillimes, 0);

  const update = (key: number, patch: Partial<Draft>) =>
    setLines((current) => current.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  return (
    <View style={styles.form}>
      <Text style={Typography.heading}>{s.title}</Text>
      {lines.map((line) => (
        <View key={line.key} style={styles.line}>
          <TextInput
            value={line.label}
            onChangeText={(label) => update(line.key, { label })}
            placeholder={s.linePlaceholder}
            placeholderTextColor={Colors.textMuted}
            maxLength={120}
            style={[styles.input, styles.label]}
          />
          <TextInput
            value={line.amount}
            onChangeText={(amount) => update(line.key, { amount })}
            placeholder="0,000"
            placeholderTextColor={Colors.textMuted}
            keyboardType="decimal-pad"
            style={[styles.input, styles.amount]}
            accessibilityLabel={s.amount}
          />
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={s.removeLine}
            hitSlop={8}
            onPress={() => setLines((current) => current.filter((l) => l.key !== line.key))}>
            <Ionicons name="close-circle-outline" size={22} color={Colors.textSecondary} />
          </Pressable>
        </View>
      ))}
      <Pressable
        accessibilityRole="button"
        onPress={() => setLines((current) => [...current, { key: Date.now(), label: '', amount: '' }])}
        style={styles.add}>
        <Ionicons name="add" size={18} color={Colors.primaryDark} />
        <Text style={styles.addText}>{s.addLine}</Text>
      </Pressable>

      <TextInput
        value={explanation}
        onChangeText={setExplanation}
        placeholder={requiresExplanation ? s.explanationRequired : s.explanation}
        placeholderTextColor={Colors.textMuted}
        multiline
        maxLength={1000}
        style={[styles.input, styles.explanation]}
      />

      <View style={styles.totalRow}>
        <Text style={Typography.bodyStrong}>{t.quote.total}</Text>
        <Text style={Typography.bodyStrong}>{formatMillimes(total)}</Text>
      </View>
      <Button label={s.send} disabled={!valid} loading={pending} onPress={() => onSubmit(complete, explanation.trim())} />
    </View>
  );
}

const styles = StyleSheet.create({
  form: { gap: Spacing.sm },
  line: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm },
  input: {
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.sm,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: Spacing.sm,
    fontSize: 15,
    color: Colors.text,
  },
  label: { flex: 1 },
  amount: { width: 92, textAlign: 'right' },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', paddingVertical: Spacing.xs },
  addText: { color: Colors.primaryDark, fontWeight: '700' },
  explanation: { minHeight: 64, textAlignVertical: 'top' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: Spacing.xs },
});
