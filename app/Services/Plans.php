<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\TenantContext;

/**
 * Protection plans: the company's monthly income.
 *
 *   Home      → monthly protection with armed response
 *   Vehicle   → tracker + armed response
 *   Business  → alarm + armed response
 *
 * Every plan includes armed response, priority handling of panic alerts and incident
 * reports, and a call-back from the control room. Plans are rows in `services`
 * (plan_for = home|vehicle|business) so their prices are edited in the price list.
 */
final class Plans
{
    public const TYPES = ['home', 'vehicle', 'business'];

    /** What each plan covers, its asset kind, and the starter price (Admin changes it in the price list). */
    public const DEFAULTS = [
        'home' => [
            'name' => 'Home protection plan', 'kind' => 'property', 'price' => 45000, 'setup' => null, 'icon' => 'house-check',
            'description' => 'Monthly protection for your home with 24/7 armed response.',
            'includes' => "24/7 armed response to your home\nPriority panic button with a call-back from the control room\nIncident reports handled first\nControl room monitoring of your address",
        ],
        'vehicle' => [
            'name' => 'Vehicle tracking plan', 'kind' => 'vehicle', 'price' => 19900, 'setup' => 150000, 'icon' => 'car-front',
            'description' => 'A tracker in your car, recovery support and armed response wherever you are.',
            'includes' => "Tracker supplied and fitted (once-off fitting fee)\n24/7 tracking and recovery support\nArmed response to your vehicle's location\nPriority panic button with a call-back",
        ],
        'business' => [
            'name' => 'Business alarm plan', 'kind' => 'property', 'price' => 85000, 'setup' => 350000, 'icon' => 'shop',
            'description' => 'An alarm system for your business, monitored 24/7, with armed response.',
            'includes' => "Alarm system supplied and installed (once-off installation fee)\n24/7 alarm monitoring by the control room\nArmed response to every alarm activation\nPriority panic button with a call-back",
        ],
    ];

    public const BUSINESS_TYPE = 'Business premises';

    /** Active plans keyed by type (home / vehicle / business). */
    public static function all(): array
    {
        $rows = DB::all('SELECT * FROM services WHERE tenant_id = ? AND plan_for IS NOT NULL AND is_active = 1 ORDER BY FIELD(plan_for, "home", "vehicle", "business")', [TenantContext::id()]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['plan_for']] ??= $r;
        }
        return $out;
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /** Which plan fits an asset: a car → vehicle; a property → business or home by its type. */
    public static function typeFor(string $kind, array $asset): string
    {
        if ($kind === 'vehicle') {
            return 'vehicle';
        }
        return ($asset['property_type'] ?? '') === self::BUSINESS_TYPE ? 'business' : 'home';
    }

    public static function forAsset(string $kind, array $asset): ?array
    {
        return self::get(self::typeFor($kind, $asset));
    }

    /** Optional extras a customer can add to a plan (add-ons and installations, not plans). */
    public static function extras(): array
    {
        return DB::all(
            'SELECT * FROM services WHERE tenant_id = ? AND is_active = 1 AND plan_for IS NULL AND category IN ("Add-ons", "Installations", "Assessments")
              ORDER BY FIELD(category, "Add-ons", "Installations", "Assessments"), sort_order, name',
            [TenantContext::id()]
        );
    }

    public static function includes(array $plan): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $plan['includes']))));
    }

    /** Create any missing plan for the company (safe to run on every start). */
    public static function seed(int $tenantId): int
    {
        $added = 0;
        $i = 0;
        foreach (self::DEFAULTS as $type => $d) {
            $i++;
            if (DB::value('SELECT 1 FROM services WHERE tenant_id = ? AND plan_for = ?', [$tenantId, $type])) {
                continue;
            }
            DB::insert('services', [
                'tenant_id' => $tenantId, 'name' => $d['name'], 'category' => 'Protection plans', 'description' => $d['description'],
                'pricing' => 'fixed', 'price_cents' => $d['price'], 'price_unit' => 'per month', 'setup_cents' => $d['setup'],
                'includes' => $d['includes'], 'plan_for' => $type, 'service_area' => area()['name'] . ' and surrounding areas',
                'availability_text' => '24/7', 'is_active' => 1, 'sort_order' => $i,
            ]);
            $added++;
        }
        return $added;
    }
}
