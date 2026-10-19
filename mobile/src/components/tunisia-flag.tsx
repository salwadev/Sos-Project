import Svg, { Circle, Polygon, Rect } from 'react-native-svg';

/** Étoile à 5 branches centrée en (cx, cy), une branche pointée vers la droite. */
function starPoints(cx: number, cy: number, outer: number, inner: number): string {
  return Array.from({ length: 10 }, (_, i) => {
    const r = i % 2 === 0 ? outer : inner;
    const angle = (i * Math.PI) / 5;
    return `${(cx + r * Math.cos(angle)).toFixed(2)},${(cy + r * Math.sin(angle)).toFixed(2)}`;
  }).join(' ');
}

/** Drapeau tunisien (les emojis drapeaux ne s'affichent pas sous Windows). */
export function TunisiaFlag({ width = 28 }: { width?: number }) {
  return (
    <Svg width={width} height={(width * 2) / 3} viewBox="0 0 30 20">
      <Rect width={30} height={20} rx={2} fill="#E70013" />
      <Circle cx={15} cy={10} r={6} fill="#FFFFFF" />
      <Circle cx={15} cy={10} r={4.5} fill="#E70013" />
      <Circle cx={16.2} cy={10} r={3.6} fill="#FFFFFF" />
      <Polygon points={starPoints(16.6, 10, 2.5, 1)} fill="#E70013" />
    </Svg>
  );
}
