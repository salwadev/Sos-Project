/**
 * Formes des réponses de l'API (backend/src/ApiResource, OpenAPI sur /api/docs).
 * Montants en millimes (1 TND = 1000 millimes), dates ISO 8601 en UTC.
 */
import type { CategoryCode, ServiceRequestStatus } from '@/types/service-request';

export type HydraCollection<T> = { member: T[]; totalItems?: number };

export type TokenPair = {
  token: string;
  refreshToken: string;
  /** Durée de vie du jeton d'accès, en secondes. */
  expiresIn: number;
  userId: string;
  roles: string[];
  newAccount: boolean;
};

export type Urgency = 'asap' | 'today';

export type Category = {
  id: string;
  code: CategoryCode | string;
  nameFr: string;
  nameAr: string;
  sensitive: boolean;
};

export type Service = {
  '@id': string;
  id: string;
  code: string;
  nameFr: string;
  nameAr: string;
  defaultTravelFeeMillimes: number | null;
  category: Category;
};

export type ServiceArea = { id: string; code: string; name: string };

export type ServiceRequest = {
  id: string;
  reference: string;
  /** IRI du service, ex. /api/services/{id}. */
  service: string;
  area: string | null;
  status: ServiceRequestStatus;
  urgency: Urgency;
  description: string;
  addressLine: string;
  landmark: string | null;
  submittedAt: string | null;
  closedAt: string | null;
  cancelReason: string | null;
  createdAt: string;
};

export type OfferStatus = 'sent' | 'reserved' | 'declined' | 'expired' | 'confirmed' | 'rejected';

export type PublicProfessional = {
  displayName: string;
  ratingAverage: number | null;
  ratingCount: number;
};

export type Offer = {
  id: string;
  status: OfferStatus;
  wave: number;
  travelFeeMillimes: number;
  sentAt: string;
  /** Délai de réponse du professionnel. */
  respondBefore: string;
  /** Fin de la réservation : le client doit confirmer avant. */
  reservedUntil: string | null;
  /** Côté professionnel : zone et distance arrondie seulement, jamais l'adresse (§6.4). */
  request: {
    id: string;
    reference: string;
    status: ServiceRequestStatus;
    serviceCode: string;
    serviceName: string;
    categoryCode: string;
    urgency: Urgency;
    description: string;
    areaName: string | null;
    approximateDistanceKm: number | null;
  };
  /** Côté client seulement. */
  professional: PublicProfessional | null;
  interventionId: string | null;
};

export type InterventionStatus =
  | 'confirmed'
  | 'on_the_way'
  | 'on_site'
  | 'working'
  | 'completion_declared'
  | 'completed'
  | 'disputed'
  | 'cancelled';

export type ProfessionalStep = 'on_the_way' | 'on_site' | 'working' | 'completion_declared';

export type QuoteStatus = 'proposed' | 'accepted' | 'rejected' | 'superseded';

export type QuoteLine = { label: string; amountMillimes: number };

export type Quote = {
  id: string;
  version: number;
  status: QuoteStatus;
  lines: QuoteLine[];
  totalMillimes: number;
  currency: string;
  explanation: string | null;
  proposedAt: string;
  decidedAt: string | null;
};

export type Intervention = {
  id: string;
  status: InterventionStatus;
  request: {
    id: string;
    reference: string;
    status: ServiceRequestStatus;
    serviceCode: string;
    serviceName: string;
    urgency: Urgency;
    description: string;
  };
  professional: PublicProfessional;
  travelFeeMillimes: number;
  /** Visible du client, et du professionnel assigné tant que l'intervention est en cours (§6.4). */
  address: { addressLine: string; landmark: string | null; latitude: number | null; longitude: number | null } | null;
  /** Estimation à présenter comme telle (§5.6). */
  estimatedArrivalAt: string | null;
  onTheWayAt: string | null;
  arrivedAt: string | null;
  workStartedAt: string | null;
  completionDeclaredAt: string | null;
  completedAt: string | null;
  quotes: Quote[];
  acceptedQuote: Quote | null;
  pendingQuote: Quote | null;
  /** Professionnel et administration seulement. */
  commission: {
    baseMillimes: number;
    rateBasisPoints: number;
    amountMillimes: number;
    currency: string;
    status: 'due' | 'paid' | 'adjusted' | 'cancelled';
  } | null;
  /** Paiement en espèces déclaré à la clôture, puis confirmé par le professionnel. */
  payment: {
    method: 'cash_direct' | 'provider';
    status: 'declared' | 'confirmed' | 'refunded';
    amountMillimes: number;
    currency: string;
  } | null;
  review: { rating: number; comment: string | null; createdAt: string } | null;
};

export type Availability = {
  available: boolean;
  availableUntil: string | null;
  lastConfirmedAt: string | null;
};

export type VerificationStatus = 'pending' | 'verified' | 'rejected' | 'suspended';

export type DocumentType = 'identity' | 'qualification' | 'company_registration' | 'insurance' | 'other';

export type ProfessionalDocument = {
  id: string;
  type: DocumentType;
  status: 'pending' | 'approved' | 'rejected' | 'expired';
  mimeType: string;
  sizeBytes: number;
  expiresAt: string | null;
  uploadedAt: string;
  reviewedAt: string | null;
  rejectionReason: string | null;
  fileUrl: string;
};

export type LegalStatus = 'individual' | 'company';

export type ProfessionalProfile = {
  id: string;
  displayName: string;
  legalStatus: LegalStatus;
  verificationStatus: VerificationStatus;
  verificationNote: string | null;
  verifiedAt: string | null;
  baseLatitude: number | null;
  baseLongitude: number | null;
  services: { serviceCode: string; serviceName: string; travelFeeMillimes: number; diagnosticFeeMillimes: number | null }[];
  serviceAreas: { code: string; name: string; active: boolean }[];
  documents: ProfessionalDocument[];
  createdAt: string;
};

export type ProfessionalProfileInput = {
  displayName: string;
  legalStatus: LegalStatus;
  services: { serviceCode: string; travelFeeMillimes: number; diagnosticFeeMillimes?: number | null }[];
  serviceAreaCodes: string[];
  baseLatitude: number;
  baseLongitude: number;
};
