import Svg, { Path } from 'react-native-svg';
import { StyleSheet, Text, View } from 'react-native';

import { Colors } from '@/constants/theme';
import { useLtrRow } from '@/i18n/i18n-context';

/** Logo « Urgence Tunisie » : pin + éclair. */
export function BrandLogo({ size = 56 }: { size?: number }) {
  const ltrRow = useLtrRow();

  return (
    <View style={[styles.row, { flexDirection: ltrRow }]}>
      <Svg width={size} height={size * 1.2} viewBox="0 0 50 60">
        <Path d="M25 2C12.3 2 2 12.1 2 24.6 2 41 25 58 25 58s23-17 23-33.4C48 12.1 37.7 2 25 2z" fill={Colors.primary} />
        <Path d="M28.5 10 15.5 28h8.2l-3.4 16L34 25.5h-8.4L28.5 10z" fill={Colors.primaryText} />
      </Svg>
      <View>
        <Text style={[styles.word, { fontSize: size * 0.46, color: Colors.navy }]}>Urgence</Text>
        <Text style={[styles.word, { fontSize: size * 0.46, color: Colors.primary }]}>Tunisie</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { alignItems: 'center', gap: 10 },
  word: { fontWeight: '800', letterSpacing: -0.3, textAlign: 'left' },
});
