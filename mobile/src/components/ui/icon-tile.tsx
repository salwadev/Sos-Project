import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import type { ComponentProps } from 'react';
import { StyleSheet, View } from 'react-native';

import { Colors, Radius } from '@/constants/theme';

export type McIconName = ComponentProps<typeof MaterialCommunityIcons>['name'];

type Props = {
  icon: McIconName;
  size?: number;
  color?: string;
  background?: string;
  round?: boolean;
};

export function IconTile({ icon, size = 44, color = Colors.primary, background = Colors.primarySoft, round }: Props) {
  return (
    <View
      style={[
        styles.tile,
        { width: size, height: size, backgroundColor: background, borderRadius: round ? size / 2 : Radius.md },
      ]}>
      <MaterialCommunityIcons name={icon} size={size * 0.55} color={color} />
    </View>
  );
}

const styles = StyleSheet.create({
  tile: { alignItems: 'center', justifyContent: 'center' },
});
