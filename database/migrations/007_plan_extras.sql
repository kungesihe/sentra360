-- Extras are matched to plans, so a plan never offers what it already includes.
-- addon_for = comma list of plan types (home, business, vehicle) the service can be added to.
ALTER TABLE services ADD COLUMN addon_for VARCHAR(40) NULL;

-- Home plan: armed response only, so alarm, monitoring, patrols and installations are real extras.
UPDATE services SET addon_for = 'home' WHERE plan_for IS NULL AND name IN ('Alarm monitoring', 'Alarm installation');
-- Business alarm plan already includes the alarm and its monitoring; guards, cameras and fencing are extras.
UPDATE services SET addon_for = 'home,business' WHERE plan_for IS NULL AND name IN ('Security patrols', 'CCTV installation', 'Access control', 'Electric fencing', 'Security assessment');
-- On-site guarding is an extra on top of the business plan, not a second monthly business plan.
UPDATE services SET addon_for = 'business', category = 'Add-ons', name = 'On-site guards (business)'
 WHERE plan_for IS NULL AND name = 'Business security' AND category = 'Business';
-- Vehicles: an escort is the only extra that applies to a car.
UPDATE services SET addon_for = 'vehicle' WHERE plan_for IS NULL AND name = 'Personal security escort';
