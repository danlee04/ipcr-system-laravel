<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How the width of the functions table is spent.
 *
 * Three of its columns hold sentences, and they are nothing like each other in
 * length. An output is a title - "Awarded Contract", four words at the most.
 * A success indicator and an accomplishment are whole sentences carrying
 * figures and deadlines. Giving the three of them equal columns spent the room
 * where it was not needed and clamped it away where it was.
 *
 * The accomplishment column is deliberately left without a width. It takes
 * whatever the row has left over, which is what keeps the table full whether
 * or not the Edit column is there - it is absent once the IPCR is submitted.
 */
class FunctionsTableLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->owner = $user->fresh();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);

        IpcrItem::factory()->create([
            'ipcr_id'           => $this->ipcr->id,
            'category'          => FunctionCategory::Core,
            'output'            => 'Awarded Contract',
            'success_indicator' => '100% Prepared Bid Evaluation Report submitted for Security Services '
                . 'bidder/s within 15 calendar days from receipt of bidding documents',
        ]);
    }

    private function html(): string
    {
        return $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();
    }

    /** The class on the header cell carrying this label, or '' when it has none. */
    private function widthOf(string $header): string
    {
        preg_match(
            '#<th[^>]*class="([^"]*)"[^>]*>\s*' . preg_quote($header, '#') . '\s*</th>#s',
            $this->html(),
            $match,
        );

        $this->assertNotEmpty($match, "No header cell found for {$header}.");

        return preg_match('/w-\[(\d+)%\]/', $match[1], $width) ? $width[1] : '';
    }

    public function test_the_output_column_is_narrower_than_the_indicator(): void
    {
        $this->assertLessThan(
            (int) $this->widthOf('Success Indicator'),
            (int) $this->widthOf('Output'),
        );
    }

    /**
     * No width at all, so it absorbs the rest of the row. Pinning it too would
     * leave a gap on a submitted IPCR, where the Edit column is gone.
     */
    public function test_the_accomplishment_column_takes_what_is_left(): void
    {
        $this->assertSame('', $this->widthOf('Actual Accomplishment'));
    }

    /** Something has to be left for it to take. */
    public function test_the_fixed_columns_do_not_fill_the_row(): void
    {
        $fixed = (int) $this->widthOf('Output')
            + (int) $this->widthOf('Success Indicator')
            + (int) $this->widthOf('Avg.');

        $this->assertLessThan(80, $fixed);
    }
}
