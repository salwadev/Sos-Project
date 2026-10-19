import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, Text, View } from 'react-native';

import { Colors } from '@/constants/theme';

type Props = {
  name: string;
  size?: number;
  verified?: boolean;
  online?: boolean;
  color?: string;
};

/** Avatar à initiales en attendant les photos de profil vérifiées. */
export function Avatar({ name, size = 48, verified, online, color = Colors.navySoft }: Props) {
  const initials = name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
  const badge = Math.max(14, size * 0.32);

  return (
    <View style={{ width: size, height: size }}>
      <View style={[styles.circle, { width: size, height: size, borderRadius: size / 2, backgroundColor: color }]}>
        <Text style={[styles.initials, { fontSize: size * 0.36 }]}>{initials}</Text>
      </View>
      {verified ? (
        <View style={[styles.badge, { width: badge, height: badge, borderRadius: badge / 2 }]}>
          <Ionicons name="checkmark" size={badge * 0.7} color={Colors.primaryText} />
        </View>
      ) : null}
      {online ? <View style={[styles.online, { width: badge * 0.75, height: badge * 0.75, borderRadius: badge }]} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  circle: { alignItems: 'center', justifyContent: 'center' },
  initials: { color: Colors.primaryText, fontWeight: '700' },
  badge: {
    position: 'absolute',
    right: -2,
    bottom: -2,
    backgroundColor: Colors.primary,
    borderWidth: 2,
    borderColor: Colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  online: {
    position: 'absolute',
    right: 0,
    bottom: 0,
    backgroundColor: Colors.success,
    borderWidth: 2,
    borderColor: Colors.background,
  },
});
