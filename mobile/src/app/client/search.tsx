import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { FakeMap } from '@/components/fake-map';
import { MapSheetLayout } from '@/components/map-sheet-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Timeline, type TimelineStep } from '@/components/ui/timeline';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockRequest, searchPros } from '@/mocks/client-flow';

/** Délai simulé avant qu'un professionnel accepte (la vraie valeur vient des vagues de diffusion, §8.3). */
const FAKE_OFFER_DELAY_MS = 4000;

export default function SearchInProgress() {
  const { t } = useI18n();
  const [offerReceived, setOfferReceived] = useState(false);

  useEffect(() => {
    const id = setTimeout(() => setOfferReceived(true), FAKE_OFFER_DELAY_MS);
    return () => clearTimeout(id);
  }, []);

  const steps: TimelineStep[] = [
    { key: 'sent', title: t.search.sent, time: mockRequest.sentAt, state: 'done' },
    {
      key: 'contacted',
      title: t.search.contacted,
      description: t.search.contactedHint,
      state: offerReceived ? 'done' : 'current',
    },
    {
      key: 'proposal',
      title: t.search.proposal,
      description: offerReceived ? t.search.proposalReady : t.search.proposalHint,
      state: offerReceived ? 'current' : 'pending',
    },
  ];

  return (
    <MapSheetLayout
      map={<FakeMap variant="search" pros={searchPros} style={StyleSheet.absoluteFill} />}
      header={<ScreenHeader overlay title={t.search.title} />}
      footer={
        <>
          {offerReceived ? <Button label={t.search.viewProposal} onPress={() => router.push('/client/proposal')} /> : null}
          <Button label={t.search.cancel} variant="outline" onPress={() => router.replace('/client')} />
        </>
      }>
      <View style={styles.titles}>
        <Text style={Typography.title}>{t.search.headline}</Text>
        <Text style={Typography.caption}>{t.search.subtitle}</Text>
      </View>

      <Card tone="soft">
        <View style={styles.row}>
          <IconTile icon="faucet" color={Colors.navy} background={Colors.background} />
          <Text style={Typography.heading}>{t.services[mockRequest.service]}</Text>
          <Text style={Typography.caption}>· {mockRequest.district}</Text>
        </View>
      </Card>

      <Timeline steps={steps} />
    </MapSheetLayout>
  );
}

const styles = StyleSheet.create({
  titles: { gap: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
});
