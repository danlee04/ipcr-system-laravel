<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Tripwire for a silent breakage.
 *
 * The Breeze modal relies on `transform` to lift the panel above the
 * backdrop. That works under Tailwind v3; under v4 `.transform` resolves to
 * `transform: none` when no rotate or skew is set, so the panel becomes static
 * and the `fixed` backdrop covers it.
 *
 * When that happens the whole modal sits under 75% gray, and every click
 * inside it lands on the backdrop, whose handler is `show = false`. The code
 * looks entirely correct while nothing in the modal can be clicked.
 *
 * An ordinary assertion cannot catch this - seeing the stacking needs a real
 * browser. So this is what we watch instead: whether the `relative z-10` that
 * fixes it is still there.
 */
class ModalStackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_modal_panel_is_lifted_above_the_backdrop(): void
    {
        $html = Blade::render(
            '<x-modal name="probe">inner content</x-modal>'
        );

        $this->assertStringContainsString(
            'relative z-10',
            $html,
            'The modal panel lost its "relative z-10". Under Tailwind v4 that puts the whole panel '
            . 'underneath the backdrop: the modal looks washed out and every click inside it closes '
            . 'the modal instead of doing anything. See the comment in components/modal.blade.php.'
        );
    }

    public function test_the_modal_still_renders_its_slot_and_backdrop(): void
    {
        $html = Blade::render('<x-modal name="probe">inner content</x-modal>');

        $this->assertStringContainsString('inner content', $html);
        $this->assertStringContainsString('bg-gray-500/75', $html);
        $this->assertStringContainsString('$event.detail == \'probe\'', $html);
    }
}
