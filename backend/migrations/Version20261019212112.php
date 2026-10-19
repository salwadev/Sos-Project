<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial (cahier des charges §13) : comptes, catalogue, zones PostGIS, demandes, offres,
 * interventions, devis, paiements, commissions, avis, réclamations, notifications, audit, OTP.
 */
final class Version20261019212112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial Urgence Tunisie (§13)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, phone VARCHAR(16) NOT NULL, roles JSON NOT NULL, status VARCHAR(16) NOT NULL, locale VARCHAR(2) NOT NULL, last_login_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9444F97DD ON app_user (phone)');
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, action VARCHAR(80) NOT NULL, actor_id VARCHAR(36) DEFAULT NULL, subject_type VARCHAR(60) NOT NULL, subject_id VARCHAR(36) DEFAULT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, previous_hash VARCHAR(64) DEFAULT NULL, hash VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F6E1C0F5D1B862B8 ON audit_log (hash)');
        $this->addSql('CREATE INDEX idx_audit_subject ON audit_log (subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_log (created_at)');
        $this->addSql('CREATE TABLE availability (id UUID NOT NULL, available BOOLEAN NOT NULL, available_until TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, last_confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, current_location geography(POINT, 4326) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, professional_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3FB7A2BFDB77003 ON availability (professional_id)');
        $this->addSql('CREATE INDEX idx_availability_window ON availability (available, available_until)');
        $this->addSql('CREATE INDEX idx_availability_location ON availability USING gist(current_location)');
        $this->addSql('CREATE TABLE client_address (id UUID NOT NULL, label VARCHAR(60) NOT NULL, address_line VARCHAR(255) NOT NULL, landmark VARCHAR(255) DEFAULT NULL, location geography(POINT, 4326) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, client_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5F732BFC19EB6921 ON client_address (client_id)');
        $this->addSql('CREATE TABLE client_profile (id UUID NOT NULL, first_name VARCHAR(80) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D36AEE72A76ED395 ON client_profile (user_id)');
        $this->addSql('CREATE TABLE commission (id UUID NOT NULL, base_millimes INT NOT NULL, rate_basis_points INT NOT NULL, amount_millimes INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(16) NOT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, intervention_id UUID NOT NULL, professional_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1C6501588EAE3863 ON commission (intervention_id)');
        $this->addSql('CREATE INDEX idx_commission_professional_status ON commission (professional_id, status)');
        $this->addSql('CREATE INDEX IDX_1C650158DB77003 ON commission (professional_id)');
        $this->addSql('CREATE TABLE complaint (id UUID NOT NULL, subject VARCHAR(40) NOT NULL, description TEXT NOT NULL, status VARCHAR(16) NOT NULL, resolution TEXT DEFAULT NULL, resolved_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, request_id UUID NOT NULL, opened_by_id UUID NOT NULL, assigned_to_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_complaint_status ON complaint (status, created_at)');
        $this->addSql('CREATE INDEX IDX_5F2732B5427EB8A5 ON complaint (request_id)');
        $this->addSql('CREATE INDEX IDX_5F2732B5AB159F5 ON complaint (opened_by_id)');
        $this->addSql('CREATE INDEX IDX_5F2732B5F4BD7827 ON complaint (assigned_to_id)');
        $this->addSql('CREATE TABLE intervention (id UUID NOT NULL, status VARCHAR(24) NOT NULL, estimated_arrival_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, on_the_way_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, arrived_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, work_started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, completion_declared_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, request_id UUID NOT NULL, offer_id UUID NOT NULL, professional_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D11814AB427EB8A5 ON intervention (request_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D11814AB53C674EE ON intervention (offer_id)');
        $this->addSql('CREATE INDEX idx_intervention_professional_status ON intervention (professional_id, status)');
        $this->addSql('CREATE INDEX IDX_D11814ABDB77003 ON intervention (professional_id)');
        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, type VARCHAR(60) NOT NULL, channel VARCHAR(8) NOT NULL, status VARCHAR(16) NOT NULL, payload JSON NOT NULL, attempts INT NOT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, recipient_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notification_status ON notification (status, created_at)');
        $this->addSql('CREATE INDEX IDX_BF5476CAE92F8F78 ON notification (recipient_id)');
        $this->addSql('CREATE TABLE otp_challenge (id UUID NOT NULL, phone VARCHAR(16) NOT NULL, code_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, attempts INT NOT NULL, consumed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_otp_phone_created ON otp_challenge (phone, created_at)');
        $this->addSql('CREATE TABLE payment (id UUID NOT NULL, method VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, gross_millimes INT NOT NULL, currency VARCHAR(3) NOT NULL, provider_reference VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, intervention_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6D28840D8EAE3863 ON payment (intervention_id)');
        $this->addSql('CREATE TABLE professional_document (id UUID NOT NULL, type VARCHAR(32) NOT NULL, storage_key VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes INT NOT NULL, status VARCHAR(16) NOT NULL, expires_at DATE DEFAULT NULL, reviewed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, professional_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_document_status_expiry ON professional_document (status, expires_at)');
        $this->addSql('CREATE INDEX IDX_4BEF50CBDB77003 ON professional_document (professional_id)');
        $this->addSql('CREATE INDEX IDX_4BEF50CBFC6B21F1 ON professional_document (reviewed_by_id)');
        $this->addSql('CREATE TABLE professional_profile (id UUID NOT NULL, display_name VARCHAR(120) NOT NULL, legal_status VARCHAR(20) NOT NULL, verification_status VARCHAR(16) NOT NULL, verification_note TEXT DEFAULT NULL, verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, base_location geography(POINT, 4326) DEFAULT NULL, rating_average DOUBLE PRECISION DEFAULT NULL, rating_count INT NOT NULL, response_rate DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E728A82A76ED395 ON professional_profile (user_id)');
        $this->addSql('CREATE INDEX idx_professional_verification ON professional_profile (verification_status)');
        $this->addSql('CREATE INDEX idx_professional_base_location ON professional_profile USING gist(base_location)');
        $this->addSql('CREATE TABLE professional_service_area (professional_profile_id UUID NOT NULL, service_area_id UUID NOT NULL, PRIMARY KEY (professional_profile_id, service_area_id))');
        $this->addSql('CREATE INDEX IDX_720E5216FB976A01 ON professional_service_area (professional_profile_id)');
        $this->addSql('CREATE INDEX IDX_720E5216728B1200 ON professional_service_area (service_area_id)');
        $this->addSql('CREATE TABLE professional_service (id UUID NOT NULL, travel_fee_millimes INT NOT NULL, diagnostic_fee_millimes INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, professional_id UUID NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_professional_service ON professional_service (professional_id, service_id)');
        $this->addSql('CREATE INDEX IDX_6EB8BA5FDB77003 ON professional_service (professional_id)');
        $this->addSql('CREATE INDEX IDX_6EB8BA5FED5CA9E6 ON professional_service (service_id)');
        $this->addSql('CREATE TABLE quote (id UUID NOT NULL, version INT NOT NULL, status VARCHAR(16) NOT NULL, lines JSON NOT NULL, total_millimes INT NOT NULL, currency VARCHAR(3) NOT NULL, explanation TEXT DEFAULT NULL, proposed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, decided_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, intervention_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_quote_version ON quote (intervention_id, version)');
        $this->addSql('CREATE INDEX IDX_6B71CBF48EAE3863 ON quote (intervention_id)');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F2195B3BC57DA ON refresh_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)');
        $this->addSql('CREATE TABLE request_offer (id UUID NOT NULL, status VARCHAR(16) NOT NULL, wave INT NOT NULL, travel_fee_millimes INT NOT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, respond_before TIMESTAMP(0) WITH TIME ZONE NOT NULL, responded_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, reserved_until TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, request_id UUID NOT NULL, professional_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_offer_professional_status ON request_offer (professional_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_offer_request_professional ON request_offer (request_id, professional_id)');
        $this->addSql('CREATE INDEX IDX_74C13B1B427EB8A5 ON request_offer (request_id)');
        $this->addSql('CREATE INDEX IDX_74C13B1BDB77003 ON request_offer (professional_id)');
        $this->addSql('CREATE TABLE review (id UUID NOT NULL, rating SMALLINT NOT NULL, comment TEXT DEFAULT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, intervention_id UUID NOT NULL, author_id UUID NOT NULL, professional_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794381C68EAE3863 ON review (intervention_id)');
        $this->addSql('CREATE INDEX idx_review_professional_status ON review (professional_id, status)');
        $this->addSql('CREATE INDEX IDX_794381C6F675F31B ON review (author_id)');
        $this->addSql('CREATE INDEX IDX_794381C6DB77003 ON review (professional_id)');
        $this->addSql('CREATE TABLE service (id UUID NOT NULL, code VARCHAR(60) NOT NULL, name_fr VARCHAR(80) NOT NULL, name_ar VARCHAR(80) NOT NULL, requires_qualification BOOLEAN NOT NULL, default_travel_fee_millimes INT DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, category_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E19D9AD277153098 ON service (code)');
        $this->addSql('CREATE INDEX IDX_E19D9AD212469DE2 ON service (category_id)');
        $this->addSql('CREATE TABLE service_area (id UUID NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, boundary geography(MULTIPOLYGON, 4326) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_19D7898477153098 ON service_area (code)');
        $this->addSql('CREATE INDEX idx_service_area_boundary ON service_area USING gist(boundary)');
        $this->addSql('CREATE TABLE service_category (id UUID NOT NULL, code VARCHAR(40) NOT NULL, name_fr VARCHAR(80) NOT NULL, name_ar VARCHAR(80) NOT NULL, sensitive BOOLEAN NOT NULL, active BOOLEAN NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF3A42FC77153098 ON service_category (code)');
        $this->addSql('CREATE TABLE service_request (id UUID NOT NULL, reference VARCHAR(12) NOT NULL, status VARCHAR(20) NOT NULL, urgency VARCHAR(10) NOT NULL, description TEXT NOT NULL, address_line VARCHAR(255) NOT NULL, landmark VARCHAR(255) DEFAULT NULL, location geography(POINT, 4326) DEFAULT NULL, submitted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, closed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, cancel_reason VARCHAR(255) DEFAULT NULL, idempotency_key VARCHAR(80) DEFAULT NULL, correlation_id UUID NOT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, client_id UUID NOT NULL, service_id UUID NOT NULL, area_id UUID DEFAULT NULL, cancelled_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F413DD03AEA34913 ON service_request (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F413DD037FD1C147 ON service_request (idempotency_key)');
        $this->addSql('CREATE INDEX idx_request_status_created ON service_request (status, created_at)');
        $this->addSql('CREATE INDEX idx_request_service_area ON service_request (service_id, area_id)');
        $this->addSql('CREATE INDEX idx_request_location ON service_request USING gist(location)');
        $this->addSql('CREATE INDEX IDX_F413DD0319EB6921 ON service_request (client_id)');
        $this->addSql('CREATE INDEX IDX_F413DD03ED5CA9E6 ON service_request (service_id)');
        $this->addSql('CREATE INDEX IDX_F413DD03BD0F409C ON service_request (area_id)');
        $this->addSql('CREATE INDEX IDX_F413DD03187B2D12 ON service_request (cancelled_by_id)');
        $this->addSql('CREATE TABLE service_request_transition (id UUID NOT NULL, transition VARCHAR(40) NOT NULL, from_status VARCHAR(20) NOT NULL, to_status VARCHAR(20) NOT NULL, reason VARCHAR(255) DEFAULT NULL, occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, request_id UUID NOT NULL, actor_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_request_transition_request ON service_request_transition (request_id, occurred_at)');
        $this->addSql('CREATE INDEX IDX_493F51DE427EB8A5 ON service_request_transition (request_id)');
        $this->addSql('CREATE INDEX IDX_493F51DE10DAF24A ON service_request_transition (actor_id)');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BFDB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE client_address ADD CONSTRAINT FK_5F732BFC19EB6921 FOREIGN KEY (client_id) REFERENCES client_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE client_profile ADD CONSTRAINT FK_D36AEE72A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT FK_1C6501588EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT FK_1C650158DB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE complaint ADD CONSTRAINT FK_5F2732B5427EB8A5 FOREIGN KEY (request_id) REFERENCES service_request (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE complaint ADD CONSTRAINT FK_5F2732B5AB159F5 FOREIGN KEY (opened_by_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE complaint ADD CONSTRAINT FK_5F2732B5F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814AB427EB8A5 FOREIGN KEY (request_id) REFERENCES service_request (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814AB53C674EE FOREIGN KEY (offer_id) REFERENCES request_offer (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABDB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE professional_document ADD CONSTRAINT FK_4BEF50CBDB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE professional_document ADD CONSTRAINT FK_4BEF50CBFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE professional_profile ADD CONSTRAINT FK_E728A82A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE professional_service_area ADD CONSTRAINT FK_720E5216FB976A01 FOREIGN KEY (professional_profile_id) REFERENCES professional_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE professional_service_area ADD CONSTRAINT FK_720E5216728B1200 FOREIGN KEY (service_area_id) REFERENCES service_area (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE professional_service ADD CONSTRAINT FK_6EB8BA5FDB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE professional_service ADD CONSTRAINT FK_6EB8BA5FED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF48EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE request_offer ADD CONSTRAINT FK_74C13B1B427EB8A5 FOREIGN KEY (request_id) REFERENCES service_request (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE request_offer ADD CONSTRAINT FK_74C13B1BDB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C68EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6DB77003 FOREIGN KEY (professional_id) REFERENCES professional_profile (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD212469DE2 FOREIGN KEY (category_id) REFERENCES service_category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0319EB6921 FOREIGN KEY (client_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03BD0F409C FOREIGN KEY (area_id) REFERENCES service_area (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request_transition ADD CONSTRAINT FK_493F51DE427EB8A5 FOREIGN KEY (request_id) REFERENCES service_request (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request_transition ADD CONSTRAINT FK_493F51DE10DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');

        // Journal d'audit en ajout seul (§7.6) : toute modification ou suppression de ligne est refusée.
        $this->addSql(<<<'SQL'
            CREATE FUNCTION audit_log_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log est en ajout seul';
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER audit_log_append_only BEFORE UPDATE OR DELETE ON audit_log FOR EACH ROW EXECUTE FUNCTION audit_log_append_only()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS audit_log_append_only ON audit_log');
        $this->addSql('DROP FUNCTION IF EXISTS audit_log_append_only()');
        $this->addSql('ALTER TABLE availability DROP CONSTRAINT FK_3FB7A2BFDB77003');
        $this->addSql('ALTER TABLE client_address DROP CONSTRAINT FK_5F732BFC19EB6921');
        $this->addSql('ALTER TABLE client_profile DROP CONSTRAINT FK_D36AEE72A76ED395');
        $this->addSql('ALTER TABLE commission DROP CONSTRAINT FK_1C6501588EAE3863');
        $this->addSql('ALTER TABLE commission DROP CONSTRAINT FK_1C650158DB77003');
        $this->addSql('ALTER TABLE complaint DROP CONSTRAINT FK_5F2732B5427EB8A5');
        $this->addSql('ALTER TABLE complaint DROP CONSTRAINT FK_5F2732B5AB159F5');
        $this->addSql('ALTER TABLE complaint DROP CONSTRAINT FK_5F2732B5F4BD7827');
        $this->addSql('ALTER TABLE intervention DROP CONSTRAINT FK_D11814AB427EB8A5');
        $this->addSql('ALTER TABLE intervention DROP CONSTRAINT FK_D11814AB53C674EE');
        $this->addSql('ALTER TABLE intervention DROP CONSTRAINT FK_D11814ABDB77003');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D8EAE3863');
        $this->addSql('ALTER TABLE professional_document DROP CONSTRAINT FK_4BEF50CBDB77003');
        $this->addSql('ALTER TABLE professional_document DROP CONSTRAINT FK_4BEF50CBFC6B21F1');
        $this->addSql('ALTER TABLE professional_profile DROP CONSTRAINT FK_E728A82A76ED395');
        $this->addSql('ALTER TABLE professional_service_area DROP CONSTRAINT FK_720E5216FB976A01');
        $this->addSql('ALTER TABLE professional_service_area DROP CONSTRAINT FK_720E5216728B1200');
        $this->addSql('ALTER TABLE professional_service DROP CONSTRAINT FK_6EB8BA5FDB77003');
        $this->addSql('ALTER TABLE professional_service DROP CONSTRAINT FK_6EB8BA5FED5CA9E6');
        $this->addSql('ALTER TABLE quote DROP CONSTRAINT FK_6B71CBF48EAE3863');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE request_offer DROP CONSTRAINT FK_74C13B1B427EB8A5');
        $this->addSql('ALTER TABLE request_offer DROP CONSTRAINT FK_74C13B1BDB77003');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C68EAE3863');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6F675F31B');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6DB77003');
        $this->addSql('ALTER TABLE service DROP CONSTRAINT FK_E19D9AD212469DE2');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD0319EB6921');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD03ED5CA9E6');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD03BD0F409C');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD03187B2D12');
        $this->addSql('ALTER TABLE service_request_transition DROP CONSTRAINT FK_493F51DE427EB8A5');
        $this->addSql('ALTER TABLE service_request_transition DROP CONSTRAINT FK_493F51DE10DAF24A');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE availability');
        $this->addSql('DROP TABLE client_address');
        $this->addSql('DROP TABLE client_profile');
        $this->addSql('DROP TABLE commission');
        $this->addSql('DROP TABLE complaint');
        $this->addSql('DROP TABLE intervention');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE otp_challenge');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE professional_document');
        $this->addSql('DROP TABLE professional_profile');
        $this->addSql('DROP TABLE professional_service_area');
        $this->addSql('DROP TABLE professional_service');
        $this->addSql('DROP TABLE quote');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE request_offer');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE service_area');
        $this->addSql('DROP TABLE service_category');
        $this->addSql('DROP TABLE service_request');
        $this->addSql('DROP TABLE service_request_transition');
    }
}
