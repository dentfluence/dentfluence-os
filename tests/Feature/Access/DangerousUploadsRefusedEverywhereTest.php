<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board 2A.3: the RejectDangerousUploads middleware refuses a script
 * on ANY upload route, including ones with no file rule of their own.
 * The profile avatar route is used as the probe.
 */
class DangerousUploadsRefusedEverywhereTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_a_php_file_renamed_jpg_is_refused_before_the_controller(): void
    {
        Storage::fake('public');
        $user = $this->legacyAdminUser();

        $this->actingAs($user)
            ->postJson(route('profile.avatar'), [
                'avatar' => UploadedFile::fake()->createWithContent('me.jpg', '<?php system($_GET["c"]);'),
            ])
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_an_svg_is_refused(): void
    {
        $user = $this->legacyAdminUser();

        $this->actingAs($user)
            ->postJson(route('profile.avatar'), [
                'avatar' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertStatus(422);
    }
}
