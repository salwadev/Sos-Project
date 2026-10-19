import Svg, { Circle, Defs, G, LinearGradient, Path, Rect, Stop } from 'react-native-svg';

import { Colors } from '@/constants/theme';

/** Scène « Sidi Bou Saïd » : façade blanche, porte bleue, mer et camionnette d'intervention. */
export function WelcomeIllustration({ height = 240 }: { height?: number }) {
  return (
    <Svg width="100%" height={height} viewBox="0 0 340 240" preserveAspectRatio="xMidYMid meet">
      <Defs>
        <LinearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
          <Stop offset="0" stopColor="#DDF1F8" />
          <Stop offset="1" stopColor="#F6FBFC" />
        </LinearGradient>
      </Defs>
      <Rect x={0} y={0} width={340} height={240} rx={24} fill="url(#sky)" />
      {/* Mer */}
      <Path d="M180 150 H340 V196 H180Z" fill="#9FD3E6" />
      <Path d="M190 160 h30 M240 172 h40 M290 158 h30" stroke="#FFFFFF" strokeWidth={2} strokeLinecap="round" />
      {/* Façade */}
      <Rect x={22} y={52} width={150} height={150} fill="#FFFFFF" />
      <Path d="M22 52 h150" stroke="#E3EAEE" strokeWidth={3} />
      {/* Porte bleue en arc */}
      <Path d="M62 202 V130 a35 35 0 0 1 70 0 V202Z" fill="#1F5FA8" />
      <Path d="M70 202 V132 a27 27 0 0 1 54 0 V202Z" fill="#2A74C4" />
      <G fill="#FFFFFF" opacity={0.85}>
        <Circle cx={84} cy={150} r={2.5} />
        <Circle cx={97} cy={150} r={2.5} />
        <Circle cx={110} cy={150} r={2.5} />
        <Circle cx={84} cy={170} r={2.5} />
        <Circle cx={97} cy={170} r={2.5} />
        <Circle cx={110} cy={170} r={2.5} />
      </G>
      {/* Fenêtre */}
      <Rect x={142} y={88} width={20} height={30} rx={10} fill="#2A74C4" />
      {/* Bougainvilliers */}
      <G fill="#E0437A">
        <Circle cx={30} cy={60} r={12} />
        <Circle cx={46} cy={50} r={10} />
        <Circle cx={22} cy={82} r={9} />
        <Circle cx={160} cy={60} r={10} />
      </G>
      <G fill="#F27AA6">
        <Circle cx={40} cy={68} r={7} />
        <Circle cx={170} cy={72} r={7} />
      </G>
      {/* Palmier */}
      <Path d="M300 150 C298 120 296 100 292 80" stroke="#8A6A45" strokeWidth={5} fill="none" strokeLinecap="round" />
      <G fill="#3AA57A">
        <Path d="M292 80 C270 70 255 78 248 90 C265 82 280 82 292 80Z" />
        <Path d="M292 80 C312 66 328 70 336 82 C318 78 304 80 292 80Z" />
        <Path d="M292 80 C286 60 292 48 304 42 C298 56 296 68 292 80Z" />
      </G>
      {/* Sol */}
      <Rect x={0} y={196} width={340} height={44} fill="#EEF2EC" />
      {/* Camionnette */}
      <G transform="translate(196 160)">
        <Path d="M0 18 a8 8 0 0 1 8 -8 H70 l18 18 V44 H0Z" fill="#FFFFFF" stroke="#D5DEE3" strokeWidth={1.5} />
        <Path d="M72 14 l12 12 H72Z" fill="#BFE3F2" />
        <Rect x={10} y={26} width={44} height={6} rx={3} fill={Colors.primary} />
        <Circle cx={20} cy={46} r={8} fill={Colors.navy} />
        <Circle cx={70} cy={46} r={8} fill={Colors.navy} />
        <Circle cx={20} cy={46} r={3} fill="#FFFFFF" />
        <Circle cx={70} cy={46} r={3} fill="#FFFFFF" />
      </G>
    </Svg>
  );
}
