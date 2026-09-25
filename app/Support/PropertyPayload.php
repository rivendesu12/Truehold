<?php

namespace App\Support;

/**
 * Decides what a listing may look like in a page the browser receives.
 *
 * The map embeds its listings as json so the markers can be filtered without
 * a round trip, and that json was the whole feed row. Two different problems
 * came out of that.
 *
 * The first is personal data. The scraped spreadsheets carry the current
 * tenant's name, their phone number and their WhatsApp in a raw_row field
 * that rode along untouched, so a public map URL served hundreds of people's
 * contact details to anyone who opened the page source. That field never
 * belongs in a browser, for an agent or anyone else.
 *
 * The second is commercial. Agency names, the source advert and the paying
 * flag are for the agent side; a client sent a map link could otherwise read
 * every sourcing relationship we have.
 *
 * Written as an allow-list for personal data and a deny-list for the rest:
 * the map's filters read a long tail of optional fields, and guessing at that
 * list would break filtering quietly, which is worse than carrying a field
 * we did not need.
 */
final class PropertyPayload
{
    /** Never sent to a browser, whoever is looking. */
    private const NEVER = [
        'raw_row',          // tenant name, phone, WhatsApp
        'tenant',
        'tenant_id',
        'tenants_phone_number',
        'whatsapp',
        'drive_folder_url', // private Drive links
        'drive_room_folder',
    ];

    /** Agent-side only. */
    private const AGENTS_ONLY = [
        'agent_name',
        'agent_phone',
        'landlord_name',
        'landlord_id',
        'landlord_profile_url',
        'management_company',
        'paying',
        'pays_commission',
        'commission_value',
        'commission_estimated',
        'commission_basis',
        'link',
        'url',
        'external_ref',
        'source',
        'spareroom_id',
    ];

    /**
     * @param  iterable<int, array>  $properties
     * @return array<int, array>
     */
    public static function forBrowser(iterable $properties, bool $isAgent): array
    {
        $out = [];

        foreach ($properties as $property) {
            $out[] = self::one((array) $property, $isAgent);
        }

        return $out;
    }

    public static function one(array $property, bool $isAgent): array
    {
        foreach (self::NEVER as $key) {
            unset($property[$key]);
        }

        if (! $isAgent) {
            foreach (self::AGENTS_ONLY as $key) {
                unset($property[$key]);
            }
        }

        // Links use the public id; a client's copy carries no internal id at
        // all, since those name the source ("spareroom-…").
        if (isset($property['id']) && ! \App\Support\PublicId::looksLikeOne((string) $property['id'])) {
            $property['public_id'] = PublicId::for((string) $property['id']);
            if (! $isAgent) {
                $property['id'] = $property['public_id'];
            }
        }

        // Keys the scrapers invent from spreadsheet headers, which is where
        // free-text personal data tends to arrive. Matched on meaning rather
        // than an exact name, because the headers change.
        foreach (array_keys($property) as $key) {
            $normalised = strtolower((string) $key);

            if (preg_match('/tenant|whatsapp|phone|email|date of birth|dob\b/', $normalised)) {
                unset($property[$key]);
            }
        }

        // The occupation columns are the one place a mis-aligned scrape puts a
        // real person's name into a field that looks legitimate, so the value
        // is checked as well as the key.
        foreach (['occupation', 'pref_occupation'] as $field) {
            $value = trim((string) ($property[$field] ?? ''));

            if ($value !== '' && ! preg_match('/available to all|student|professional|working|employed|any\b|no preference/i', $value)) {
                $property[$field] = null;
            }
        }

        return $property;
    }
}
