import { StyleSheet, Text, type StyleProp, type TextStyle } from 'react-native';

import { Colors } from '@/constants/theme';
import { formatTND } from '@/utils/format';

/** Montant mis en avant : chiffres en grand, « TND » plus petit, comme sur les relevés. */
export function Amount({ value, size = 24, style }: { value: number; size?: number; style?: StyleProp<TextStyle> }) {
  const [number, currency] = formatTND(value).split(/ (?=TND$)/);
  return (
    <Text style={[styles.number, { fontSize: size }, style]} numberOfLines={1}>
      {number}
      <Text style={[styles.currency, { fontSize: size * 0.62 }]}> {currency}</Text>
    </Text>
  );
}

const styles = StyleSheet.create({
  number: { fontWeight: '800', color: Colors.text, writingDirection: 'ltr' },
  currency: { fontWeight: '800' },
});
