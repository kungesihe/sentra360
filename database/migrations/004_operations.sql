-- SENTRA 360 — Phases 2–6: incidents, properties & vehicles, applications,
-- quotations, payments, services & bookings, panic alerts, control room,
-- response assignments, emergency contacts, safety alerts, patrols, files.
-- Compatible with MySQL, MariaDB and TiDB (one change per ALTER).

-- Uploaded evidence/photos/documents, stored in the database so they survive
-- restarts on hosts with temporary disks.
CREATE TABLE IF NOT EXISTS files (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  owner_type    VARCHAR(30)  NOT NULL,
  owner_id      BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime          VARCHAR(60)  NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL,
  data          MEDIUMBLOB   NOT NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_files_owner (tenant_id, owner_type, owner_id),
  CONSTRAINT fk_files_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incident_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  group_code  ENUM('crime','emergency') NOT NULL,
  priority    ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_inc_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registered_properties (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id           INT UNSIGNED NOT NULL,
  owner_id            INT UNSIGNED NOT NULL,
  name                VARCHAR(120) NOT NULL,
  property_type       VARCHAR(40)  NOT NULL,
  address             VARCHAR(255) NOT NULL,
  suburb              VARCHAR(100) NOT NULL,
  city                VARCHAR(100) NOT NULL,
  latitude            DECIMAL(10,7) NULL,
  longitude           DECIMAL(10,7) NULL,
  existing_security   TEXT NULL,
  access_requirements TEXT NULL,
  security_concerns   TEXT NULL,
  additional_info     TEXT NULL,
  protection_status   ENUM('none','active','cancelled') NOT NULL DEFAULT 'none',
  activated_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_props_owner (tenant_id, owner_id),
  CONSTRAINT fk_props_owner FOREIGN KEY (owner_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registered_vehicles (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id         INT UNSIGNED NOT NULL,
  owner_id          INT UNSIGNED NOT NULL,
  make              VARCHAR(60)  NOT NULL,
  model             VARCHAR(60)  NOT NULL,
  registration      VARCHAR(20)  NOT NULL,
  colour            VARCHAR(40)  NOT NULL,
  year              SMALLINT UNSIGNED NULL,
  vin               VARCHAR(30)  NULL,
  primary_driver    VARCHAR(120) NULL,
  additional_info   TEXT NULL,
  protection_status ENUM('none','active','cancelled') NOT NULL DEFAULT 'none',
  activated_at      DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_veh_owner (tenant_id, owner_id),
  CONSTRAINT fk_veh_owner FOREIGN KEY (owner_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_applications (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id          INT UNSIGNED NOT NULL,
  reference          VARCHAR(30)  NOT NULL,
  property_id        INT UNSIGNED NOT NULL,
  applicant_id       INT UNSIGNED NOT NULL,
  services_requested TEXT NULL,
  notes              TEXT NULL,
  status             ENUM('draft','submitted','under_review','assessment_required','quotation_prepared','quotation_sent','awaiting_customer_acceptance','awaiting_payment','approved','active','rejected','cancelled') NOT NULL DEFAULT 'draft',
  submitted_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_papp_ref (reference),
  KEY ix_papp_status (tenant_id, status),
  CONSTRAINT fk_papp_property FOREIGN KEY (property_id) REFERENCES registered_properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_papp_applicant FOREIGN KEY (applicant_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_applications (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id          INT UNSIGNED NOT NULL,
  reference          VARCHAR(30)  NOT NULL,
  vehicle_id         INT UNSIGNED NOT NULL,
  applicant_id       INT UNSIGNED NOT NULL,
  services_requested TEXT NULL,
  notes              TEXT NULL,
  status             ENUM('draft','submitted','under_review','assessment_required','quotation_prepared','quotation_sent','awaiting_customer_acceptance','awaiting_payment','approved','active','rejected','cancelled') NOT NULL DEFAULT 'draft',
  submitted_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vapp_ref (reference),
  KEY ix_vapp_status (tenant_id, status),
  CONSTRAINT fk_vapp_vehicle FOREIGN KEY (vehicle_id) REFERENCES registered_vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_vapp_applicant FOREIGN KEY (applicant_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id         INT UNSIGNED NOT NULL,
  name              VARCHAR(120) NOT NULL,
  category          VARCHAR(60)  NOT NULL,
  description       TEXT NOT NULL,
  pricing           ENUM('fixed','from','quote') NOT NULL DEFAULT 'quote',
  price_cents       INT UNSIGNED NULL,
  price_unit        VARCHAR(40)  NULL,
  duration_text     VARCHAR(100) NULL,
  service_area      VARCHAR(190) NULL,
  availability_text VARCHAR(190) NULL,
  requirements      TEXT NULL,
  photo_file_id     BIGINT UNSIGNED NULL,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_services_tenant (tenant_id, is_active),
  CONSTRAINT fk_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A request becomes a booking once confirmed (status 'confirmed').
CREATE TABLE IF NOT EXISTS service_requests (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id      INT UNSIGNED NOT NULL,
  reference      VARCHAR(30)  NOT NULL,
  service_id     INT UNSIGNED NOT NULL,
  customer_id    INT UNSIGNED NOT NULL,
  requested_date DATE NOT NULL,
  requested_time TIME NULL,
  location_text  VARCHAR(255) NOT NULL,
  duration_text  VARCHAR(100) NULL,
  instructions   TEXT NULL,
  status         ENUM('submitted','under_review','awaiting_customer_acceptance','awaiting_payment','confirmed','completed','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sreq_ref (reference),
  KEY ix_sreq_status (tenant_id, status),
  CONSTRAINT fk_sreq_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_sreq_customer FOREIGN KEY (customer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotations (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id      INT UNSIGNED NOT NULL,
  reference      VARCHAR(30)  NOT NULL,
  customer_id    INT UNSIGNED NOT NULL,
  subject_type   ENUM('property','vehicle','service') NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  status         ENUM('draft','sent','accepted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  billing        ENUM('once_off','monthly') NOT NULL DEFAULT 'once_off',
  valid_until    DATE NULL,
  notes          TEXT NULL,
  include_vat    TINYINT(1) NOT NULL DEFAULT 1,
  subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
  vat_cents      INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents    INT UNSIGNED NOT NULL DEFAULT 0,
  created_by     INT UNSIGNED NULL,
  sent_at        DATETIME NULL,
  responded_at   DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quote_ref (reference),
  KEY ix_quote_subject (tenant_id, subject_type, subject_id),
  KEY ix_quote_customer (tenant_id, customer_id),
  CONSTRAINT fk_quote_customer FOREIGN KEY (customer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id        INT UNSIGNED NOT NULL,
  quotation_id     INT UNSIGNED NOT NULL,
  description      VARCHAR(255) NOT NULL,
  quantity         DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price_cents INT UNSIGNED NOT NULL,
  line_total_cents INT UNSIGNED NOT NULL,
  sort_order       SMALLINT NOT NULL DEFAULT 0,
  KEY ix_qitems_quote (quotation_id),
  CONSTRAINT fk_qitems_quote FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id          INT UNSIGNED NOT NULL,
  reference          VARCHAR(30)  NOT NULL,
  customer_id        INT UNSIGNED NOT NULL,
  quotation_id       INT UNSIGNED NULL,
  amount_cents       INT UNSIGNED NOT NULL,
  method             ENUM('eft','cash','card','payfast','debit_order','other') NOT NULL,
  status             ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  provider_reference VARCHAR(100) NULL,
  notes              VARCHAR(255) NULL,
  recorded_by        INT UNSIGNED NULL,
  paid_at            DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_ref (reference),
  KEY ix_pay_customer (tenant_id, customer_id),
  KEY ix_pay_quote (quotation_id),
  CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidents (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id           INT UNSIGNED NOT NULL,
  reference           VARCHAR(30)  NOT NULL,
  source              ENUM('community','panic','staff') NOT NULL DEFAULT 'community',
  reporter_id         INT UNSIGNED NULL,
  category_id         INT UNSIGNED NULL,
  title               VARCHAR(150) NOT NULL,
  priority            ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status              ENUM('submitted','received','under_review','assigned','in_progress','resolved','closed') NOT NULL DEFAULT 'submitted',
  occurred_at         DATETIME NOT NULL,
  location_text       VARCHAR(255) NULL,
  latitude            DECIMAL(10,7) NULL,
  longitude           DECIMAL(10,7) NULL,
  description         TEXT NULL,
  witness_details     TEXT NULL,
  injuries            TEXT NULL,
  assistance_required TINYINT(1) NOT NULL DEFAULT 0,
  property_id         INT UNSIGNED NULL,
  vehicle_id          INT UNSIGNED NULL,
  received_at         DATETIME NULL,
  resolved_at         DATETIME NULL,
  resolution          TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inc_ref (reference),
  KEY ix_inc_status (tenant_id, status),
  KEY ix_inc_created (tenant_id, created_at),
  KEY ix_inc_reporter (tenant_id, reporter_id),
  CONSTRAINT fk_inc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_inc_category FOREIGN KEY (category_id) REFERENCES incident_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline for incidents, applications, service requests and quotations.
CREATE TABLE IF NOT EXISTS workflow_events (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  subject_type  VARCHAR(30) NOT NULL,
  subject_id    BIGINT UNSIGNED NOT NULL,
  from_status   VARCHAR(40) NULL,
  to_status     VARCHAR(40) NULL,
  note          TEXT NULL,
  is_internal   TINYINT(1) NOT NULL DEFAULT 0,
  actor_id      INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_wf_subject (tenant_id, subject_type, subject_id),
  CONSTRAINT fk_wf_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incident_assignments (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  incident_id  BIGINT UNSIGNED NOT NULL,
  officer_id   INT UNSIGNED NOT NULL,
  assigned_by  INT UNSIGNED NULL,
  status       ENUM('assigned','accepted','en_route','on_scene','completed','declined') NOT NULL DEFAULT 'assigned',
  assigned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at  DATETIME NULL,
  arrived_at   DATETIME NULL,
  completed_at DATETIME NULL,
  KEY ix_assign_officer (tenant_id, officer_id, status),
  KEY ix_assign_incident (incident_id),
  CONSTRAINT fk_assign_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_officer FOREIGN KEY (officer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panic_alerts (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id        INT UNSIGNED NOT NULL,
  reference        VARCHAR(30)  NOT NULL,
  customer_id      INT UNSIGNED NOT NULL,
  incident_id      BIGINT UNSIGNED NULL,
  property_id      INT UNSIGNED NULL,
  vehicle_id       INT UNSIGNED NULL,
  latitude         DECIMAL(10,7) NULL,
  longitude        DECIMAL(10,7) NULL,
  accuracy_m       INT UNSIGNED NULL,
  location_status  ENUM('captured','denied','unavailable') NOT NULL DEFAULT 'unavailable',
  silent           TINYINT(1) NOT NULL DEFAULT 0,
  extra_info       VARCHAR(500) NULL,
  status           ENUM('submitted','delivered','acknowledged','dispatched','resolved','cancelled') NOT NULL DEFAULT 'submitted',
  delivered_at     DATETIME NULL,
  acknowledged_at  DATETIME NULL,
  acknowledged_by  INT UNSIGNED NULL,
  dispatched_at    DATETIME NULL,
  resolved_at      DATETIME NULL,
  cancelled_at     DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_panic_ref (reference),
  KEY ix_panic_status (tenant_id, status),
  CONSTRAINT fk_panic_customer FOREIGN KEY (customer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panic_locations (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id      INT UNSIGNED NOT NULL,
  panic_alert_id BIGINT UNSIGNED NOT NULL,
  latitude       DECIMAL(10,7) NOT NULL,
  longitude      DECIMAL(10,7) NOT NULL,
  accuracy_m     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ploc_alert (panic_alert_id),
  CONSTRAINT fk_ploc_alert FOREIGN KEY (panic_alert_id) REFERENCES panic_alerts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_contacts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  name            VARCHAR(120) NOT NULL,
  relationship    VARCHAR(60)  NULL,
  mobile          VARCHAR(20)  NOT NULL,
  notify_on_panic TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ec_user (tenant_id, user_id),
  CONSTRAINT fk_ec_user FOREIGN KEY (user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS safety_alerts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  body         TEXT NOT NULL,
  level        ENUM('info','warning','urgent') NOT NULL DEFAULT 'info',
  area         VARCHAR(150) NULL,
  published_by INT UNSIGNED NULL,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NULL,
  KEY ix_alerts_tenant (tenant_id, published_at),
  CONSTRAINT fk_alerts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patrol_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  officer_id  INT UNSIGNED NOT NULL,
  area        VARCHAR(150) NOT NULL,
  checkpoint  VARCHAR(150) NULL,
  notes       TEXT NULL,
  latitude    DECIMAL(10,7) NULL,
  longitude   DECIMAL(10,7) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_patrol_tenant (tenant_id, created_at),
  CONSTRAINT fk_patrol_officer FOREIGN KEY (officer_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER body;

-- How customers pay (EFT banking details etc.), shown on accepted quotations.
ALTER TABLE tenant_settings ADD COLUMN payment_instructions TEXT NULL;
