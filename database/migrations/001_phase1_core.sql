-- SENTRA 360 — Phase 1 core schema
-- Multi-tenant SaaS foundation: plans, tenants, branding, subscriptions,
-- platform (owner) users, tenant users, roles/permissions, sessions,
-- verification codes, notifications outbox, audit logs, consents.
--
-- Tenant isolation at database level: every tenant-owned table carries
-- tenant_id, and child rows reference (id, tenant_id) pairs through
-- composite foreign keys, so a row can never point at another tenant's data.

SET NAMES utf8mb4;

CREATE TABLE subscription_plans (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                VARCHAR(40)  NOT NULL,
  name                VARCHAR(100) NOT NULL,
  description         VARCHAR(500) NULL,
  price_monthly_cents INT UNSIGNED NOT NULL DEFAULT 0,
  price_annual_cents  INT UNSIGNED NOT NULL DEFAULT 0,
  setup_fee_cents     INT UNSIGNED NOT NULL DEFAULT 0,
  trial_days          SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  features            JSON NOT NULL,
  limits_json         JSON NULL,
  is_public           TINYINT(1) NOT NULL DEFAULT 1,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  sort_order          SMALLINT NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenants (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                CHAR(36)     NOT NULL,
  slug                VARCHAR(40)  NOT NULL,
  name                VARCHAR(150) NOT NULL,
  custom_domain       VARCHAR(190) NULL,
  status              ENUM('pending','trial','active','suspended','rejected','cancelled') NOT NULL DEFAULT 'pending',
  contact_name        VARCHAR(150) NOT NULL,
  contact_email       VARCHAR(190) NOT NULL,
  contact_mobile      VARCHAR(20)  NOT NULL,
  registration_number VARCHAR(60)  NULL,
  psira_number        VARCHAR(60)  NULL,
  is_demo             TINYINT(1)   NOT NULL DEFAULT 0,
  status_reason       VARCHAR(500) NULL,
  approved_at         DATETIME NULL,
  suspended_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenants_uuid (uuid),
  UNIQUE KEY uq_tenants_slug (slug),
  UNIQUE KEY uq_tenants_domain (custom_domain),
  KEY ix_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_settings (
  tenant_id           INT UNSIGNED PRIMARY KEY,
  display_name        VARCHAR(150) NOT NULL,
  logo_path           VARCHAR(255) NULL,
  primary_color       CHAR(7) NOT NULL DEFAULT '#0B3D91',
  secondary_color     CHAR(7) NOT NULL DEFAULT '#111827',
  welcome_message     VARCHAR(255) NULL,
  about               TEXT NULL,
  services_offered    TEXT NULL,
  contact_phone       VARCHAR(30)  NULL,
  contact_email       VARCHAR(190) NULL,
  physical_address    VARCHAR(255) NULL,
  emergency_phone     VARCHAR(30)  NULL,
  emergency_notes     VARCHAR(500) NULL,
  terms_text          MEDIUMTEXT NULL,
  privacy_text        MEDIUMTEXT NULL,
  notification_sender VARCHAR(100) NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id             INT UNSIGNED NOT NULL,
  plan_id               INT UNSIGNED NOT NULL,
  status                ENUM('pending','trial','active','past_due','suspended','cancelled','expired') NOT NULL DEFAULT 'pending',
  billing_cycle         ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
  price_cents           INT UNSIGNED NOT NULL DEFAULT 0,
  setup_fee_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  trial_ends_at         DATETIME NULL,
  current_period_start  DATETIME NULL,
  current_period_end    DATETIME NULL,
  cancelled_at          DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_subs_tenant (tenant_id),
  KEY ix_subs_status (status),
  CONSTRAINT fk_subs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subs_plan   FOREIGN KEY (plan_id)   REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(190) NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  role                ENUM('owner','support','finance') NOT NULL DEFAULT 'owner',
  status              ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_login_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until        DATETIME NULL,
  last_login_at       DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  is_staff    TINYINT(1)   NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL,
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                CHAR(36)     NOT NULL,
  tenant_id           INT UNSIGNED NOT NULL,
  role_id             INT UNSIGNED NOT NULL,
  email               VARCHAR(190) NOT NULL,
  mobile              VARCHAR(20)  NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  status              ENUM('pending','active','suspended','disabled') NOT NULL DEFAULT 'pending',
  client_status       ENUM('community','client') NOT NULL DEFAULT 'community',
  email_verified_at   DATETIME NULL,
  mobile_verified_at  DATETIME NULL,
  failed_login_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until        DATETIME NULL,
  last_login_at       DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_uuid (uuid),
  UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  UNIQUE KEY uq_users_tenant_mobile (tenant_id, mobile),
  UNIQUE KEY uq_users_id_tenant (id, tenant_id),
  KEY ix_users_tenant_role (tenant_id, role_id),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_role   FOREIGN KEY (role_id)   REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_profiles (
  user_id     INT UNSIGNED PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  first_name  VARCHAR(100) NOT NULL,
  surname     VARCHAR(100) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profiles_user_tenant FOREIGN KEY (user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash    CHAR(64)     NOT NULL,
  user_type     ENUM('user','platform') NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  tenant_id     INT UNSIGNED NULL,
  ip_address    VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at    DATETIME NULL,
  UNIQUE KEY uq_sessions_token (token_hash),
  KEY ix_sessions_user (user_type, user_id),
  CONSTRAINT fk_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE verification_codes (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  purpose      ENUM('verify_mobile','verify_email','password_reset') NOT NULL,
  code_hash    CHAR(64) NOT NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at   DATETIME NOT NULL,
  consumed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_codes_user (user_id, purpose),
  CONSTRAINT fk_codes_user_tenant FOREIGN KEY (user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbox for every email / SMS / in-app message. status 'not_configured'
-- means NO provider is set up and the message was NOT delivered.
CREATE TABLE notifications (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NULL,
  user_id      INT UNSIGNED NULL,
  channel      ENUM('email','sms','in_app') NOT NULL,
  recipient    VARCHAR(190) NOT NULL,
  subject      VARCHAR(255) NULL,
  body         TEXT NOT NULL,
  status       ENUM('queued','sent','failed','not_configured') NOT NULL DEFAULT 'queued',
  provider     VARCHAR(40)  NULL,
  error        VARCHAR(500) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at      DATETIME NULL,
  read_at      DATETIME NULL,
  KEY ix_notif_tenant (tenant_id, created_at),
  KEY ix_notif_user (user_id),
  CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_rate_bucket (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NULL,
  actor_type   ENUM('user','platform','system','guest') NOT NULL,
  actor_id     INT UNSIGNED NULL,
  action       VARCHAR(80)  NOT NULL,
  entity_type  VARCHAR(60)  NULL,
  entity_id    VARCHAR(60)  NULL,
  details      JSON NULL,
  ip_address   VARCHAR(45)  NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_tenant (tenant_id, created_at),
  KEY ix_audit_action (action),
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POPIA: record of each consent given or withdrawn.
CREATE TABLE consents (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id        INT UNSIGNED NOT NULL,
  user_id          INT UNSIGNED NOT NULL,
  consent_type     VARCHAR(60) NOT NULL,
  document_version VARCHAR(20) NOT NULL,
  granted          TINYINT(1)  NOT NULL,
  ip_address       VARCHAR(45) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_consents_user (user_id),
  CONSTRAINT fk_consents_user_tenant FOREIGN KEY (user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Owner support access to a tenant must be explicitly granted by that tenant and is audited.
CREATE TABLE support_access_grants (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id          INT UNSIGNED NOT NULL,
  platform_user_id   INT UNSIGNED NULL,
  granted_by_user_id INT UNSIGNED NOT NULL,
  reason             VARCHAR(255) NOT NULL,
  expires_at         DATETIME NOT NULL,
  revoked_at         DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_grants_tenant (tenant_id),
  CONSTRAINT fk_grants_granter FOREIGN KEY (granted_by_user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE,
  CONSTRAINT fk_grants_platform FOREIGN KEY (platform_user_id) REFERENCES platform_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE demo_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name  VARCHAR(150) NOT NULL,
  contact_name  VARCHAR(150) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(30)  NOT NULL,
  company_size  VARCHAR(40)  NULL,
  message       TEXT NULL,
  status        ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(30)  NULL,
  subject     VARCHAR(190) NOT NULL,
  message     TEXT NOT NULL,
  status      ENUM('new','handled') NOT NULL DEFAULT 'new',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
