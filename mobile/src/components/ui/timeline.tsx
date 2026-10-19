import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, Text, View } from 'react-native';

import { Colors, Spacing } from '@/constants/theme';

export type TimelineStep = {
  key: string;
  title: string;
  description?: string;
  time?: string;
  state: 'done' | 'current' | 'pending';
};

/** Suivi vertical des statuts (« Demande envoyée », « Professionnels contactés »…). */
export function Timeline({ steps }: { steps: TimelineStep[] }) {
  return (
    <View>
      {steps.map((step, index) => {
        const last = index === steps.length - 1;
        return (
          <View key={step.key} style={styles.row}>
            <View style={styles.rail}>
              <Dot state={step.state} />
              {!last ? <View style={[styles.line, step.state === 'done' && styles.lineDone]} /> : null}
            </View>
            <View style={[styles.body, !last && styles.bodySpacing]}>
              <View style={styles.titleRow}>
                <Text style={[styles.title, step.state === 'pending' && styles.titlePending]}>{step.title}</Text>
                {step.time ? <Text style={styles.time}>{step.time}</Text> : null}
              </View>
              {step.description ? <Text style={styles.description}>{step.description}</Text> : null}
            </View>
          </View>
        );
      })}
    </View>
  );
}

function Dot({ state }: { state: TimelineStep['state'] }) {
  if (state === 'done') {
    return (
      <View style={[styles.dot, styles.dotDone]}>
        <Ionicons name="checkmark" size={16} color={Colors.primaryText} />
      </View>
    );
  }
  if (state === 'current') {
    return (
      <View style={[styles.dot, styles.dotCurrent]}>
        <View style={styles.dotCurrentInner} />
      </View>
    );
  }
  return <View style={[styles.dot, styles.dotPending]} />;
}

const DOT = 26;

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: Spacing.md },
  rail: { alignItems: 'center', width: DOT },
  line: { flex: 1, width: 2, backgroundColor: Colors.border, marginVertical: 2 },
  lineDone: { backgroundColor: Colors.primary },
  body: { flex: 1, paddingTop: 2 },
  bodySpacing: { paddingBottom: Spacing.lg },
  titleRow: { flexDirection: 'row', justifyContent: 'space-between', gap: Spacing.sm },
  title: { fontSize: 15, fontWeight: '700', color: Colors.text },
  titlePending: { color: Colors.textSecondary, fontWeight: '600' },
  time: { fontSize: 13, color: Colors.textSecondary },
  description: { fontSize: 13, color: Colors.textSecondary, marginTop: 2 },
  dot: { width: DOT, height: DOT, borderRadius: DOT / 2, alignItems: 'center', justifyContent: 'center' },
  dotDone: { backgroundColor: Colors.primary },
  dotCurrent: { borderWidth: 2, borderColor: Colors.primary, backgroundColor: Colors.background },
  dotCurrentInner: { width: 14, height: 14, borderRadius: 7, backgroundColor: Colors.primary },
  dotPending: { borderWidth: 2, borderColor: Colors.border, backgroundColor: Colors.background },
});
