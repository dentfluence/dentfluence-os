<?php

namespace Tests\Feature\Settings;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Slice 2 — calendar colour source.
 *
 * The card-colour logic itself is client-side (renderEvent() and the Settings
 * live preview), so what is testable server-side is the contract those two
 * share: the stored value. `auto` must be accepted and must be what an
 * unconfigured clinic gets, otherwise the calendar silently keeps painting two
 * competing colour dimensions.
 */
class CalendarColorSourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_auto_is_an_accepted_colour_source(): void
    {
        $this->actingAs($this->userForSystemRole('admin'))
            ->post(route('settings.calendar.save'), [
                'card_style'   => 'strip',
                'color_source' => 'auto',
            ])
            ->assertRedirect();

        $this->assertSame('auto', AppSetting::get('calendar_color_source'));
    }

    public function test_explicit_choices_still_save(): void
    {
        $admin = $this->userForSystemRole('admin');

        foreach (['doctor', 'treatment'] as $source) {
            $this->actingAs($admin)
                ->post(route('settings.calendar.save'), [
                    'card_style'   => 'filled',
                    'color_source' => $source,
                ])
                ->assertRedirect();

            $this->assertSame($source, AppSetting::get('calendar_color_source'));
        }
    }

    public function test_an_unknown_colour_source_is_rejected(): void
    {
        $this->actingAs($this->userForSystemRole('admin'))
            ->post(route('settings.calendar.save'), [
                'card_style'   => 'strip',
                'color_source' => 'rainbow',
            ])
            ->assertSessionHasErrors('color_source');
    }

    public function test_an_unconfigured_clinic_gets_auto_on_the_calendar(): void
    {
        // No calendar settings saved at all — the calendar page must still
        // render, and must fall back to `auto` rather than to a fixed
        // dimension. Asserted through the rendered page so the Blade default
        // and the controller cannot drift apart.
        $this->actingAs($this->userForSystemRole('front_desk'))
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('colorSource: "auto"', false);
    }
}
