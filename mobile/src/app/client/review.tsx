import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';
import Svg, { Circle, G, Line, Path } from 'react-native-svg';

import { ProSummary } from '@/components/pro-summary';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { StarInput } from '@/components/ui/stars';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockQuote } from '@/mocks/client-flow';
import { formatTND } from '@/utils/format';

const MAX_COMMENT = 500;

/** Avis possible uniquement pour une intervention terminée et confirmée (§5.7). */
export default function Review() {
  const { t } = useI18n();
  const [rating, setRating] = useState(4);
  const [comment, setComment] = useState('');

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      footer={
        <>
          <Button label={t.review.publish} disabled={rating === 0} onPress={() => router.dismissTo('/client')} />
          <Button label={t.review.support} variant="ghost" onPress={() => router.push('/client/help')} />
        </>
      }>
      <View style={styles.hero}>
        <SuccessMark />
        <Text style={[Typography.title, styles.center]}>{t.review.headline}</Text>
        <Text style={[Typography.caption, styles.center]}>{t.review.subtitle}</Text>
      </View>

      <Card>
        <ProSummary chevron />
      </Card>

      <Text style={Typography.heading}>{t.review.question}</Text>
      <StarInput value={rating} onChange={setRating} />

      <View style={styles.commentBox}>
        <Text style={Typography.small}>
          {t.review.comment} · {t.newRequest.optional}
        </Text>
        <TextInput
          value={comment}
          onChangeText={setComment}
          placeholder={t.review.commentPlaceholder}
          placeholderTextColor={Colors.textMuted}
          multiline
          maxLength={MAX_COMMENT}
          style={styles.comment}
        />
        <Text style={styles.counter}>
          {comment.length}/{MAX_COMMENT}
        </Text>
      </View>

      <Card>
        <View style={styles.summaryRow}>
          <IconTile icon="tools" color={Colors.text} background={Colors.surface} size={40} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{t.quote.job}</Text>
            <Text style={Typography.small}>{t.review.directPayment}</Text>
          </View>
          <Text style={Typography.bodyStrong}>{formatTND(mockQuote.total)}</Text>
        </View>
      </Card>
    </Screen>
  );
}

function SuccessMark() {
  const rays = [-60, -30, 0, 30, 60, 120, 150, 180, 210, 240];
  return (
    <Svg width={110} height={110} viewBox="0 0 110 110">
      <G stroke={Colors.primary} strokeWidth={3} strokeLinecap="round">
        {rays.map((deg) => {
          const rad = (deg * Math.PI) / 180;
          return (
            <Line
              key={deg}
              x1={55 + Math.cos(rad) * 42}
              y1={55 + Math.sin(rad) * 42}
              x2={55 + Math.cos(rad) * 50}
              y2={55 + Math.sin(rad) * 50}
            />
          );
        })}
      </G>
      <Circle cx={55} cy={55} r={32} fill={Colors.success} />
      <Path d="M41 56 L51 66 L70 46" stroke="#FFFFFF" strokeWidth={6} fill="none" strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

const styles = StyleSheet.create({
  hero: { alignItems: 'center', gap: Spacing.xs },
  center: { textAlign: 'center' },
  commentBox: { borderWidth: 1, borderColor: Colors.border, borderRadius: Radius.md, padding: Spacing.md, gap: Spacing.xs },
  comment: { minHeight: 72, fontSize: 16, color: Colors.text, textAlignVertical: 'top' },
  counter: { ...Typography.small, alignSelf: 'flex-end' },
  summaryRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
});
