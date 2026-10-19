/** États d'une demande — miroir de la machine à états du backend (cahier des charges §8.4). */
export type ServiceRequestStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'SEARCHING'
  | 'OFFERED'
  | 'ACCEPTED'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'EXPIRED'
  | 'DISPUTED';

export type CategoryCode = 'home' | 'car' | 'health' | 'other';
