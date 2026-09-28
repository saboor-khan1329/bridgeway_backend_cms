<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Support\FrontendCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Audit issue 06 — corrects the location parent assignments that drive the
 * "{Parent} Sub Location" card subtitle in the "Areas We Cover" section.
 *
 * Many locations were assigned to the wrong hub (London suburbs under
 * Manchester, Manchester suburbs under London, South-West/South-East towns and
 * North/South London under Birmingham, etc.). This command moves each to the
 * correct UK hub, and sets distinct cities / no-hub towns to standalone
 * (parent_id = NULL → no subtitle).
 *
 * SAFE: only updates parent_id + card_description on the listed rows inside a
 * transaction. No rows are created or deleted. Run with --dry-run first to
 * preview, then without to apply. Idempotent — re-running changes nothing once
 * the data is correct.
 */
class LocationsFixParents extends Command
{
    protected $signature = 'locations:fix-parents {--dry-run : Preview the changes without writing}';

    protected $description = 'Correct location parent assignments + sub-location labels (audit issue 06). No data loss.';

    /** Hub location id => location ids that should sit under it. */
    private const TO_HUB = [
        1 => [52, 56, 60, 71, 81, 82, 88, 89, 95, 98, 106, 116, 117, 118, 131], // London
        2 => [40, 78, 84, 85, 94, 103, 110, 111, 112, 120, 128],                // Manchester
        3 => [53, 72],                                                           // Birmingham
    ];

    /**
     * No sensible hub — make standalone (NULL parent, no "Sub Location" label).
     * Includes Leeds (92): already parent NULL but carrying a stale
     * "London Sub Location" subtitle that needs clearing.
     */
    private const TO_STANDALONE = [
        11, 13, 16, 19, 23, 25, 26, 41, 54, 58, 65, 67, 74, 91, 92, 93, 96, 97, 99, 100, 102, 126, 129,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Derive each hub's subtitle from the hub row itself so the label always
        // matches the real parent, e.g. "London Sub Location".
        $hubLabel = [];
        foreach (array_keys(self::TO_HUB) as $hubId) {
            $hub = Location::find($hubId);
            if (! $hub) {
                $this->error("Hub location #{$hubId} not found — aborting, no changes made.");

                return self::FAILURE;
            }
            $city = trim(str_ireplace('Security Services in', '', (string) $hub->title));
            $hubLabel[$hubId] = $city.' Sub Location';
        }

        // Build the desired end-state: id => [parent_id, card_description].
        $plan = [];
        foreach (self::TO_HUB as $hubId => $ids) {
            foreach ($ids as $id) {
                $plan[$id] = [$hubId, $hubLabel[$hubId]];
            }
        }
        foreach (self::TO_STANDALONE as $id) {
            $plan[$id] = [null, null];
        }

        $rows = Location::whereIn('id', array_keys($plan))->get()->keyBy('id');

        $changed = 0;
        $missing = [];

        DB::beginTransaction();
        try {
            foreach ($plan as $id => [$parentId, $card]) {
                $location = $rows->get($id);
                if (! $location) {
                    $missing[] = $id;

                    continue;
                }

                $sameParent = (string) $location->parent_id === (string) $parentId;
                $sameCard = $location->card_description === $card;
                if ($sameParent && $sameCard) {
                    continue; // already correct
                }

                $this->line(sprintf(
                    '  #%-3d %-48s parent %s -> %s',
                    $id,
                    $location->title,
                    $location->parent_id ?? 'NULL',
                    $parentId ?? 'NULL (standalone)'
                ));

                if (! $dryRun) {
                    $location->parent_id = $parentId;
                    $location->card_description = $card;
                    $location->saveQuietly(); // skip model events; cache bumped once below
                }
                $changed++;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("DRY RUN — {$changed} location(s) would change. No data written.");
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back — no changes made: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($missing !== []) {
            $this->warn('Skipped (ids not found): '.implode(', ', $missing));
        }

        if (! $dryRun) {
            FrontendCache::bump(); // invalidate cached location payloads + ping frontend
            $this->info("Done — {$changed} location(s) updated. Frontend cache bumped.");
        }

        return self::SUCCESS;
    }
}
