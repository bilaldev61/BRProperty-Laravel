<?php

namespace App\Support;

use App\Models\Property;

/**
 * A listing, written out as plain sentences a language model can be held to.
 *
 * This is the single source of what the AI features may say about a property.
 * The reply drafter and the public assistant both read it, so a fact cannot be
 * true in one and missing from the other. Anything not produced here - price
 * flexibility, possession, paperwork - is unknown by definition.
 */
class PropertyFacts
{
    /** @return list<string> */
    public static function lines(Property $property): array
    {
        $lines = [
            'Address: ' . $property->shortAddress(),
            'Listing status: ' . $property->statusLabel(),
            'Price as advertised: ' . $property->priceDisplay(),
            'Property type: ' . $property->typeLabel(),
        ];

        if ($property->hasRooms()) {
            $lines[] = sprintf(
                'Bedrooms: %s. Bathrooms: %s. Car spaces: %s.',
                $property->bedrooms ?: 'not recorded',
                $property->bathrooms ?: 'not recorded',
                $property->carspaces ?: 'not recorded',
            );
        }

        if ($land = $property->landDisplay()) {
            $lines[] = 'Land size: ' . $land;
        }

        if ($floor = $property->floorDisplay()) {
            $lines[] = 'Covered area: ' . $floor;
        }

        if (filled($property->features)) {
            $lines[] = 'Listed features: ' . implode(', ', (array) $property->features);
        }

        // What it costs to run and what comes with it. Both are on the portals,
        // and both are the kind of thing a buyer asks before booking a viewing.
        $completion = $property->completionLabel();

        if ($completion || filled($property->handover)) {
            $lines[] = 'Completion: ' . implode(', ', array_filter([
                $completion,
                filled($property->handover) ? 'handover ' . $property->handover : null,
            ])) . '.';
        }

        if ($furnishing = $property->furnishingLabel()) {
            $lines[] = 'Furnishing: ' . $furnishing . '.';
        }

        if (filled($property->view_type)) {
            $lines[] = 'View: ' . $property->view_type . '.';
        }

        if ($ownership = $property->ownershipLabel()) {
            $lines[] = 'Ownership: ' . $ownership . '.';
        }

        if ($serviceCharge = $property->serviceChargeDisplay()) {
            $lines[] = 'Service charge: ' . $serviceCharge . '.';
        }

        if (filled($property->payment_terms)) {
            $lines[] = 'Payment terms as advertised: ' . $property->payment_terms . '.';
        }

        if (filled($property->permit_number)) {
            $lines[] = 'Advertising permit number: ' . $property->permit_number . '.';
        }

        // Landmarks, one line per kind, so the model can quote a distance
        // instead of guessing one - which it otherwise will, plausibly.
        foreach ($property->nearbyByType() as $type => $places) {
            $lines[] = $property->nearbyTypeLabel($type) . ': ' . implode('; ', array_map(
                fn(array $place) => $place['name'] . (filled($place['distance'] ?? null) ? ' — ' . $place['distance'] : ''),
                $places,
            )) . '.';
        }

        if (filled($property->inspection_times) && $property->status !== 'sold') {
            $lines[] = 'Advertised inspection times: ' . $property->inspection_times;
        }

        if (filled($property->description)) {
            $lines[] = 'Listing description: ' . $property->description;
        }

        return $lines;
    }
}
