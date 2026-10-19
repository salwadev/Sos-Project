import { Platform, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import Svg, { Circle, G, Path, Rect, Text as SvgText } from 'react-native-svg';

import { Colors } from '@/constants/theme';

/**
 * Carte factice du secteur La Marsa / Carthage, dessinée en SVG.
 * À remplacer par une vraie carte (react-native-maps + tuiles) quand le fournisseur cartographique sera choisi (§20).
 */
type Pro = { initials: string; x: number; y: number };

type Props = {
  variant: 'search' | 'tracking';
  pros?: Pro[];
  style?: StyleProp<ViewStyle>;
};

const VIEW_W = 400;
const MAP_FONT = Platform.OS === 'web' ? 'system-ui, "Segoe UI", Roboto, sans-serif' : undefined;
const VIEW_H = 420;

export function FakeMap({ variant, pros = [], style }: Props) {
  return (
    <View style={[styles.container, style]}>
      <Svg width="100%" height="100%" viewBox={`0 0 ${VIEW_W} ${VIEW_H}`} preserveAspectRatio="xMidYMid slice">
        <Rect width={VIEW_W} height={VIEW_H} fill={Colors.map.land} />
        <Base />
        {variant === 'search' ? <SearchLayer pros={pros} /> : <TrackingLayer />}
      </Svg>
    </View>
  );
}

function Base() {
  const { map } = Colors;
  return (
    <G>
      {/* Parcs et quartiers */}
      <Path d="M40 260 C70 240 120 250 130 290 C120 330 60 330 40 300Z" fill={map.landAlt} />
      <Path d="M150 60 C190 40 230 70 220 110 C200 140 150 120 150 60Z" fill={map.landAlt} />
      {/* Mer : baie de Tunis */}
      <Path
        d="M270 0 H400 V420 H330 C300 380 318 330 300 300 C282 270 310 235 296 200 C282 170 300 140 286 110 C272 80 282 40 270 0Z"
        fill={map.sea}
      />
      {/* Routes secondaires */}
      <G stroke={map.road} strokeWidth={5} fill="none" strokeLinecap="round">
        <Path d="M0 120 C80 130 160 110 280 115" />
        <Path d="M0 220 C90 215 170 230 298 210" />
        <Path d="M60 0 C70 120 50 260 90 420" />
        <Path d="M180 0 C170 110 200 230 170 420" />
        <Path d="M0 330 C110 320 200 340 320 330" />
        <Path d="M110 160 L230 170 L250 260" />
      </G>
      {/* Axe principal */}
      <Path d="M0 380 C100 300 160 260 230 180 C260 140 262 80 276 0" stroke={map.roadMajor} strokeWidth={7} fill="none" />
      <G fill={map.label} fontWeight="600" fontFamily={MAP_FONT}>
        <SvgText x={120} y={150} fontSize={22}>
          La Marsa
        </SvgText>
        <SvgText x={300} y={92} fontSize={13}>
          Sidi Bou Said
        </SvgText>
        <SvgText x={220} y={300} fontSize={18}>
          Carthage
        </SvgText>
        <SvgText x={300} y={30} fontSize={12} fontStyle="italic" fill="#5C93AD">
          Baie de Tunis
        </SvgText>
      </G>
    </G>
  );
}

function SearchLayer({ pros }: { pros: Pro[] }) {
  const cx = 180;
  const cy = 200;
  return (
    <G>
      <Circle cx={cx} cy={cy} r={110} fill={Colors.primary} fillOpacity={0.12} stroke={Colors.primary} strokeOpacity={0.35} />
      <Circle cx={cx} cy={cy} r={60} fill={Colors.primary} fillOpacity={0.12} />
      <Pin x={cx} y={cy} />
      {pros.map((p) => (
        <G key={p.initials}>
          <Circle cx={p.x} cy={p.y} r={21} fill={Colors.background} />
          <Circle cx={p.x} cy={p.y} r={18} fill={Colors.navySoft} />
          <SvgText x={p.x} y={p.y + 5} fontSize={13} fontWeight="700" fontFamily={MAP_FONT} fill={Colors.primaryText} textAnchor="middle">
            {p.initials}
          </SvgText>
          <Circle cx={p.x + 13} cy={p.y + 15} r={6} fill={Colors.success} stroke={Colors.background} strokeWidth={2} />
        </G>
      ))}
    </G>
  );
}

function TrackingLayer() {
  return (
    <G>
      <Path
        d="M120 330 C150 300 180 290 200 250 C215 220 240 205 262 170 L270 140"
        stroke={Colors.primary}
        strokeWidth={6}
        fill="none"
        strokeLinecap="round"
      />
      {/* Domicile du client */}
      <Circle cx={270} cy={120} r={22} fill={Colors.navy} />
      <Path d="M259 122 L270 111 L281 122 V132 H274 V125 H266 V132 H259Z" fill={Colors.primaryText} />
      <Circle cx={270} cy={150} r={5} fill={Colors.navy} />
      {/* Véhicule du professionnel */}
      <Circle cx={120} cy={330} r={26} fill={Colors.background} />
      <Circle cx={120} cy={330} r={22} fill={Colors.primary} />
      <Path d="M106 323 H124 V336 H106Z M124 327 H131 L135 332 V336 H124Z" fill={Colors.primaryText} />
      <Circle cx={111} cy={338} r={3} fill={Colors.primaryText} />
      <Circle cx={129} cy={338} r={3} fill={Colors.primaryText} />
    </G>
  );
}

function Pin({ x, y }: { x: number; y: number }) {
  return (
    <G transform={`translate(${x - 18}, ${y - 46})`}>
      <Path d="M18 0C8 0 0 8 0 18c0 13 18 28 18 28s18-15 18-28C36 8 28 0 18 0z" fill={Colors.primary} />
      <Circle cx={18} cy={18} r={7} fill={Colors.background} />
    </G>
  );
}

const styles = StyleSheet.create({
  container: { overflow: 'hidden', backgroundColor: Colors.map.land },
});
