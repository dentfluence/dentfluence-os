<?php

namespace Tests\Unit;

use App\Models\Prescription\PrescriptionItem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The prescription brand name must never carry the strength.
 *
 * Regression: the panel saved "brand + strength" as drug_name and the edit
 * form re-appended the strength on every reload, so a re-saved item printed
 * "Zerodol P 100+500mg 100+500mg" with the strength a third time underneath.
 * displayName() is the single reader that guarantees a clean brand — for new
 * rows and for legacy rows that already accumulated copies.
 */
class PrescriptionItemNameTest extends TestCase
{
    // No database: displayName() is pure string work on an unsaved model.

    private function item(?string $name, ?string $strength): PrescriptionItem
    {
        $item = new PrescriptionItem();
        $item->drug_name = $name;
        $item->strength  = $strength;

        return $item;
    }

    #[DataProvider('names')]
    public function test_display_name_never_contains_the_strength(?string $name, ?string $strength, string $expected): void
    {
        $this->assertSame($expected, $this->item($name, $strength)->displayName());
    }

    public static function names(): array
    {
        return [
            'clean brand stays untouched'   => ['Zerodol P', '100+500mg', 'Zerodol P'],
            'strength appended once'        => ['Zerodol P 100+500mg', '100+500mg', 'Zerodol P'],
            'legacy double append'          => ['Zerodol P 100+500mg 100+500mg', '100+500mg', 'Zerodol P'],
            'number in brand is kept'       => ['Mox 500 500mg 500mg', '500mg', 'Mox 500'],
            'pan 40'                        => ['Pan 40 40mg 40mg', '40mg', 'Pan 40'],
            'percentage strength'           => ['Chlorhex 0.2% 0.2%', '0.2%', 'Chlorhex'],
            'spaced unit'                   => ['Metrogyl 400 400 mg', '400 mg', 'Metrogyl 400'],
            'free typed drug, no strength'  => ['Amoxicillin 500mg', null, 'Amoxicillin 500mg'],
            'no name at all'                => [null, '500mg', '—'],
        ];
    }

    public function test_name_with_strength_prints_the_strength_exactly_once(): void
    {
        $this->assertSame('Zerodol P 100+500mg', $this->item('Zerodol P 100+500mg 100+500mg', '100+500mg')->nameWithStrength());
        $this->assertSame('Mox 500 500mg', $this->item('Mox 500', '500mg')->nameWithStrength());
        $this->assertSame('Hexidine', $this->item('Hexidine', null)->nameWithStrength());
    }
}
