-- Keep it simple: monthly plans (income every month) + once-off services where our
-- people are needed. Monitoring, patrols, installations and assessments are hidden
-- (kept, not deleted; the Admin can tick "Listed" to bring one back).
UPDATE services SET is_active = 0
 WHERE plan_for IS NULL
   AND name IN ('Alarm monitoring', 'Security patrols', 'Alarm installation', 'CCTV installation', 'Access control', 'Electric fencing', 'Security assessment');

-- Guards are booked when needed (per shift), like escorts and event security.
UPDATE services SET category = 'Guarding', price_unit = 'per shift', name = 'On-site guards'
 WHERE plan_for IS NULL AND name = 'On-site guards (business)';

-- Extras are no longer offered with plans.
UPDATE services SET addon_for = NULL;
