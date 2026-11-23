<?php

namespace Tests\Feature\Analysis;

use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_guests_cannot_open_or_submit_anything(): void
    {
        $analysis = Analysis::factory()->create();

        $this->get('/analyses')->assertRedirect('/login');
        $this->get('/analyses/new')->assertRedirect('/login');
        $this->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => 'x = 1'])->assertRedirect('/login');
        $this->get("/analyses/{$analysis->public_id}")->assertRedirect('/login');
        $this->get("/analyses/{$analysis->public_id}/status")->assertRedirect('/login');
        $this->assertSame(1, Analysis::count());
        Queue::assertNothingPushed();
    }

    public function test_pasted_code_is_stored_privately_and_queued(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => "import os\nos.system(cmd)\n", 'title' => 'My check'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $analysis = Analysis::sole();
        $this->assertSame([$user->id, 'My check', 'queued', 'paste'], [$analysis->user_id, $analysis->title, $analysis->status, $analysis->origin]);
        $file = $analysis->files()->sole();
        $this->assertSame(['pasted.py', 'python', 2], [$file->name, $file->language, $file->line_count]);
        $this->assertSame("analyses/{$analysis->public_id}/0.src", $file->storage_path);
        Storage::disk('local')->assertExists($file->storage_path);
        $this->assertStringNotContainsString('public', Storage::disk('local')->path($file->storage_path), 'source is never under the web root');
        Queue::assertPushed(RunAnalysis::class, fn ($job) => $job->analysisId === $analysis->id);
    }

    public function test_uploaded_file_names_never_reach_the_filesystem(): void
    {
        $this->actingAs(User::factory()->create())->post('/analyses', ['mode' => 'upload', 'files' => [
            $this->upload('../../../etc/cron.d/evil.php', "<?php\necho 1;\n"),
            $this->upload('utils.py', "x = 1\n"),
            $this->upload('utils.py', "y = 2\n"),
        ]])->assertSessionHasNoErrors();

        $analysis = Analysis::sole();
        $this->assertSame(['evil.php', 'utils.py', 'utils-2.py'], $analysis->files()->orderBy('id')->pluck('name')->all());
        $this->assertSame(
            ["analyses/{$analysis->public_id}/0.src", "analyses/{$analysis->public_id}/1.src", "analyses/{$analysis->public_id}/2.src"],
            $analysis->files()->orderBy('id')->pluck('storage_path')->all(),
        );
    }

    public function test_bad_uploads_are_refused_and_nothing_is_stored_or_queued(): void
    {
        config(['codesentinel.limits.max_files' => 2, 'codesentinel.limits.max_file_bytes' => 2048]);
        $user = User::factory()->create();
        $cases = [
            'binary disguised as python' => [$this->upload('a.py', "\x7fELF\x00\x01\x02\x03")],
            'unsupported extension' => [$this->upload('run.sh', "rm -rf /\n")],
            'executable' => [$this->upload('tool.exe', 'MZ')],
            'too large' => [$this->upload('big.py', str_repeat('a = 1 # padding'."\n", 300))],
            'too many' => [$this->upload('a.py', 'a=1'), $this->upload('b.py', 'b=1'), $this->upload('c.py', 'c=1')],
            'one good one bad' => [$this->upload('ok.py', 'a=1'), $this->upload('photo.png', "\x89PNG\r\n")],
        ];

        foreach ($cases as $label => $files) {
            $response = $this->actingAs($user)->post('/analyses', ['mode' => 'upload', 'files' => $files]);
            $this->assertNotEmpty(session('errors')?->getBag('default')->all(), "Expected an error for: {$label}");
            $response->assertRedirect();
        }

        $this->assertSame(0, Analysis::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_pasted_code_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => ''])->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/analyses', ['mode' => 'paste', 'language' => 'rb', 'code' => 'puts 1'])->assertSessionHasErrors('language');
        $this->actingAs($user)->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => "ok\0binary"])->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/analyses', ['mode' => 'somethingelse'])->assertSessionHasErrors('mode');
        $this->assertSame(0, Analysis::count());
    }

    public function test_new_analyses_are_rate_limited_per_user(): void
    {
        config(['codesentinel.limits.analyses_per_hour' => 2]);
        $user = User::factory()->create();
        $send = fn () => $this->actingAs($user)->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => 'x = 1']);

        $send()->assertSessionHasNoErrors();
        $send()->assertSessionHasNoErrors();
        $send()->assertSessionHasErrors('mode');
        $this->assertSame(2, Analysis::count());

        // Another user is not affected.
        $this->actingAs(User::factory()->create())->post('/analyses', ['mode' => 'paste', 'language' => 'py', 'code' => 'x = 1'])->assertSessionHasNoErrors();
    }
}
