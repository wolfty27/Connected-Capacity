<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\RegionArea;
use Illuminate\Database\Seeder;

/**
 * RegionSeeder - Seeds Toronto/GTA regions with FSA prefix mappings.
 *
 * Creates a metadata-driven region mapping system using FSA (Forward Sortation Area)
 * prefixes (first 3 characters of Canadian postal codes).
 *
 * IMPORTANT: All region assignment logic uses this metadata.
 * Never hardcode region assignments in PHP business logic.
 *
 * Toronto FSA Reference:
 * - M (first letter) = Toronto Census Metropolitan Area
 * - Second character: 1-9 = Urban, A-Z = Rural/Suburban
 * - Third character: Further subdivision
 *
 * @see https://en.wikipedia.org/wiki/List_of_M_postal_codes_of_Canada
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Toronto/GTA regions and FSA mappings...');

        // Create regions
        $regions = $this->createRegions();

        // Create FSA mappings for each region
        $this->createFsaMappings($regions);

        $this->command->info('Region seeding complete: ' . count($regions) . ' regions created.');
    }

    /**
     * Create the main Toronto/GTA regions.
     *
     * These regions align with Ontario Health at Home (OHAH) service areas.
     */
    protected function createRegions(): array
    {
        $regionData = [
            [
                'code' => 'TORONTO_CENTRAL',
                'name' => 'Toronto Central',
                'ohah_code' => 'TC',
            ],
            [
                'code' => 'CENTRAL_EAST',
                'name' => 'Central East',
                'ohah_code' => 'CE',
            ],
            [
                'code' => 'CENTRAL_WEST',
                'name' => 'Central West',
                'ohah_code' => 'CW',
            ],
            [
                'code' => 'NORTH',
                'name' => 'North York / North',
                'ohah_code' => 'NY',
            ],
            [
                'code' => 'EAST',
                'name' => 'East Toronto / Scarborough',
                'ohah_code' => 'ET',
            ],
        ];

        $regions = [];
        foreach ($regionData as $data) {
            $region = Region::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'ohah_code' => $data['ohah_code'],
                    'is_active' => true,
                ]
            );
            $regions[$data['code']] = $region;
            $this->command->info("  Created region: {$data['name']} ({$data['code']})");
        }

        return $regions;
    }

    /**
     * Create FSA prefix mappings for each region.
     *
     * These mappings are based on actual Toronto postal code geography.
     * FSA = Forward Sortation Area (first 3 characters of postal code).
     */
    protected function createFsaMappings(array $regions): void
    {
        // Toronto Central FSAs - Downtown core and surrounding areas
        // Includes: Financial District, Entertainment District, Yorkville, Annex, Cabbagetown
        $torontoCentralFsas = [
            // Downtown Core
            'M5A', // St. Lawrence, King East
            'M5B', // Garden District, Ryerson
            'M5C', // St. Lawrence
            'M5E', // Berczy Park
            'M5G', // Central Bay Street, Discovery District (hospitals)
            'M5H', // Adelaide, Financial District
            'M5J', // Union Station, Harbourfront East
            'M5K', // Toronto Islands
            'M5L', // Commerce Court
            'M5N', // Roselawn
            'M5P', // Forest Hill North, Forest Hill West
            'M5R', // The Annex, South Hill
            'M5S', // University of Toronto, Harbord
            'M5T', // Chinatown, Kensington Market
            'M5V', // King West, Entertainment District
            'M5W', // Toronto Dominion Centre
            'M5X', // First Canadian Place
            'M4K', // The Danforth, Riverdale
            'M4M', // Riverside
            'M4S', // Davisville
            'M4T', // Moore Park, Summerhill
            'M4V', // Deer Park, Summerhill West
            'M4W', // Rosedale
            'M4X', // St. James Town, Cabbagetown
            'M4Y', // Church-Wellesley Village
            'M6G', // Christie, Ossington
            'M6H', // Dufferin Grove, Little Portugal
            'M6J', // Little Portugal, Trinity-Bellwoods
            'M6K', // Parkdale, Exhibition Place
            'M6R', // Parkdale, Roncesvalles
            'M6S', // Runnymede, Swansea
        ];

        // Central East FSAs - East of Downtown toward Scarborough
        // Includes: Leslieville, Beaches, East York
        $centralEastFsas = [
            'M4C', // The Beaches
            'M4E', // The Beach
            'M4G', // Leaside
            'M4H', // Thorncliffe Park
            'M4J', // East York
            'M4L', // The Beaches
            'M4N', // Lawrence Park
            'M4P', // Davisville North
            'M4R', // North Toronto
        ];

        // Central West FSAs - Etobicoke and West Toronto
        // Includes: Etobicoke, High Park, Junction
        $centralWestFsas = [
            'M6A', // Lawrence Manor, Lawrence Heights
            'M6B', // Glencairn
            'M6C', // Humewood-Cedarvale
            'M6E', // Caledonia-Fairbank
            'M6L', // Downsview
            'M6M', // Del Ray, Mount Dennis
            'M6N', // Runnymede, The Junction North
            'M6P', // High Park, The Junction South
            'M8V', // New Toronto, Mimico
            'M8W', // Alderwood, Long Branch
            'M8X', // The Kingsway, Montgomery Road
            'M8Y', // Old Mill South, Humber Bay
            'M8Z', // Mimico, South of Bloor
            'M9A', // Islington Avenue
            'M9B', // West Deane Park, Princess-Rosethorn
            'M9C', // Eringate, Bloordale Gardens
            'M9P', // Westmount
            'M9R', // Martin Grove, Richview
            'M9V', // Albion Gardens, Silverstone
            'M9W', // Northwest Etobicoke
        ];

        // North FSAs - North York and northern areas
        // Includes: Willowdale, North York Centre, Don Mills
        $northFsas = [
            'M2H', // Hillcrest Village
            'M2J', // Fairview, Oriole
            'M2K', // Bayview Village
            'M2L', // Silver Hills, York Mills
            'M2M', // Willowdale, Newtonbrook
            'M2N', // Willowdale, Willowdale East
            'M2P', // York Mills West
            'M2R', // Armour Heights, Willowdale
            'M3A', // Parkwoods
            'M3B', // Don Mills
            'M3C', // Flemingdon Park, Don Mills
            'M3H', // Bathurst Manor, Wilson Heights
            'M3J', // Northwood Park, York University
            'M3K', // Downsview
            'M3L', // Downsview
            'M3M', // Downsview
            'M3N', // Downsview
            'M4A', // Victoria Village
        ];

        // East FSAs - Scarborough
        // Includes: Scarborough, Agincourt, Malvern
        $eastFsas = [
            'M1B', // Malvern, Rouge
            'M1C', // Rouge Hill, Port Union, Highland Creek
            'M1E', // West Hill, Morningside
            'M1G', // Woburn
            'M1H', // Cedarbrae
            'M1J', // Scarborough Village
            'M1K', // Kennedy Park, Ionview
            'M1L', // Golden Mile, Oakridge
            'M1M', // Cliffside, Cliffcrest
            'M1N', // Birch Cliff, Cliffside West
            'M1P', // Dorset Park, Wexford
            'M1R', // Wexford, Maryvale
            'M1S', // Agincourt
            'M1T', // Tam O'Shanter, Sullivan
            'M1V', // Milliken, Agincourt North
            'M1W', // Steeles East, L'Amoreaux
            'M1X', // Agincourt North, Steeles East
        ];

        // Create mappings for each region
        $this->createAreaMappings($regions['TORONTO_CENTRAL'], $torontoCentralFsas);
        $this->createAreaMappings($regions['CENTRAL_EAST'], $centralEastFsas);
        $this->createAreaMappings($regions['CENTRAL_WEST'], $centralWestFsas);
        $this->createAreaMappings($regions['NORTH'], $northFsas);
        $this->createAreaMappings($regions['EAST'], $eastFsas);

        $totalFsas = count($torontoCentralFsas) + count($centralEastFsas) +
                     count($centralWestFsas) + count($northFsas) + count($eastFsas);
        $this->command->info("  Created {$totalFsas} FSA prefix mappings.");
    }

    /**
     * Create area mappings for a specific region.
     */
    protected function createAreaMappings(Region $region, array $fsaPrefixes): void
    {
        foreach ($fsaPrefixes as $fsa) {
            RegionArea::updateOrCreate(
                ['fsa_prefix' => strtoupper($fsa)],
                [
                    'region_id' => $region->id,
                    // Bounding boxes can be added later for edge case handling
                    'min_lat' => null,
                    'max_lat' => null,
                    'min_lng' => null,
                    'max_lng' => null,
                ]
            );
        }
    }
}
