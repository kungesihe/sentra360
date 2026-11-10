-- Protection plans: one monthly plan per kind of asset (home, vehicle, business).
-- Plans are rows in `services` with plan_for set; they are subscribed to from the
-- homeowner's protection list, not booked in the services marketplace.
ALTER TABLE services ADD COLUMN plan_for VARCHAR(20) NULL;
ALTER TABLE services ADD COLUMN setup_cents INT UNSIGNED NULL;
ALTER TABLE services ADD COLUMN includes TEXT NULL;

-- Armed response is part of every plan, so the separate service is hidden (kept, not deleted).
UPDATE services SET is_active = 0 WHERE name = 'Armed response' AND category = 'Residential';
-- The other monthly residential services become optional add-ons to a plan.
UPDATE services SET category = 'Add-ons' WHERE category = 'Residential' AND name IN ('Alarm monitoring', 'Security patrols');

-- Panic call-back (the control room calls the person back, e.g. on WhatsApp).
ALTER TABLE panic_alerts ADD COLUMN callback_at DATETIME NULL;
ALTER TABLE panic_alerts ADD COLUMN callback_by INT UNSIGNED NULL;
ALTER TABLE panic_alerts ADD COLUMN callback_method VARCHAR(20) NULL;

-- Company WhatsApp number for call-backs and "WhatsApp the control room".
ALTER TABLE tenant_settings ADD COLUMN whatsapp_number VARCHAR(20) NULL;
