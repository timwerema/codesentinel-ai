<?php

namespace Tests\Feature\Analysis;

use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private function analysisFor(User $user, array $over = []): Analysis
    {
        $analysis = Analysis::factory()->for($user)->create($over);
        Storage::disk('local')->put("analyses/{$analysis->public_id}/0.src", "secret_plan = 1\n");
        $file = $analysis->files()->create(['name' => 'plan.py', 'language' => 'python', 'size_bytes' => 16, 'line_count' => 1, 'sha256' => str_repeat('a', 64), 'storage_path' => "analyses/{$analysis->public_id}/0.src"]);
        $analysis->findings()->create(['analysis_file_id' => $file->id, 'source' => 'semgrep', 'verified' => true, 'rule_id' => 'r', 'category' => 'security', 'severity' => 'high', 'title' => 'Owner only finding', 'explanation' => 'E', 'line_start' => 1, 'line_end' => 1, 'fingerprint' => str_repeat('b', 40)]);

        return $analysis;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_the_owner_sees_their_analysis_with_code_and_findings(): void
    {
        $owner = User::factory()->create();
        $analysis = $this->analysisFor($owner);

        $this->actingAs($owner)->get("/analyses/{$analysis->public_id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('analyses/show')
            ->where('files.0.contents', "secret_plan = 1\n")
            ->where('findings.0.title', 'Owner only finding')
            ->missing('files.0.storage_path'));
        $this->actingAs($owner)->getJson("/analyses/{$analysis->public_id}/status")->assertOk()->assertExactJson(['status' => 'completed']);
    }

    public function test_another_user_cannot_read_poll_retry_or_delete_it(): void
    {
        $analysis = $this->analysisFor(User::factory()->create(), ['status' => Analysis::FAILED]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/analyses/{$analysis->public_id}")->assertForbidden()->assertDontSee('secret_plan');
        $this->actingAs($stranger)->getJson("/analyses/{$analysis->public_id}/status")->assertForbidden();
        $this->actingAs($stranger)->post("/analyses/{$analysis->public_id}/retry")->assertForbidden();
        $this->actingAs($stranger)->delete("/analyses/{$analysis->public_id}")->assertForbidden();

        $this->assertSame(Analysis::FAILED, $analysis->fresh()->status);
        Storage::disk('local')->assertExists("analyses/{$analysis->public_id}/0.src");
        Queue::assertNothingPushed();
    }

    public function test_analyses_cannot_be_reached_by_their_numeric_id(): void
    {
        $owner = User::factory()->create();
        $analysis = $this->analysisFor($owner);

        $this->actingAs($owner)->get("/analyses/{$analysis->id}")->assertNotFound();
    }

    public function test_lists_and_totals_only_include_the_users_own_work(): void
    {
        $me = User::factory()->create();
        $this->analysisFor($me, ['title' => 'Mine']);
        $this->analysisFor(User::factory()->create(), ['title' => 'Theirs']);

        $this->actingAs($me)->get('/analyses')->assertOk()->assertInertia(fn (Assert $p) => $p->where('analyses.total', 1)->where('analyses.data.0.title', 'Mine'));
        $this->actingAs($me)->get('/analyses?q=Theirs')->assertInertia(fn (Assert $p) => $p->where('analyses.total', 0));
        $this->actingAs($me)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('counts.analyses', 1)->where('counts.findings', 1)->where('severities.high', 1)->has('recent', 1));
    }

    public function test_search_and_status_filters_work_and_treat_wildcards_as_text(): void
    {
        $me = User::factory()->create();
        $this->analysisFor($me, ['title' => 'Login handler']);
        $this->analysisFor($me, ['title' => 'Payments', 'status' => Analysis::FAILED]);

        $this->actingAs($me)->get('/analyses?q=login')->assertInertia(fn (Assert $p) => $p->where('analyses.total', 1));
        $this->actingAs($me)->get('/analyses?q=plan.py')->assertInertia(fn (Assert $p) => $p->where('analyses.total', 2));
        $this->actingAs($me)->get('/analyses?status=failed')->assertInertia(fn (Assert $p) => $p->where('analyses.total', 1)->where('analyses.data.0.title', 'Payments'));
        $this->actingAs($me)->get('/analyses?q=%25')->assertInertia(fn (Assert $p) => $p->where('analyses.total', 0));
        $this->actingAs($me)->get("/analyses?q=' OR 1=1 --")->assertOk()->assertInertia(fn (Assert $p) => $p->where('analyses.total', 0));
        $this->actingAs($me)->get('/analyses?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_the_owner_can_retry_a_failed_analysis_and_delete_with_its_stored_code(): void
    {
        $owner = User::factory()->create();
        $failed = $this->analysisFor($owner, ['status' => Analysis::FAILED, 'error' => 'boom']);
        $done = $this->analysisFor($owner);

        $this->actingAs($owner)->post("/analyses/{$done->public_id}/retry")->assertForbidden();
        $this->actingAs($owner)->post("/analyses/{$failed->public_id}/retry")->assertRedirect();
        $this->assertSame([Analysis::QUEUED, null], [$failed->fresh()->status, $failed->fresh()->error]);
        Queue::assertPushed(RunAnalysis::class, 1);

        $this->actingAs($owner)->delete("/analyses/{$done->public_id}")->assertRedirect('/analyses');
        $this->assertNull($done->fresh());
        Storage::disk('local')->assertMissing("analyses/{$done->public_id}/0.src");
        $this->assertDatabaseCount('findings', 1);
    }

    public function test_every_page_sends_security_headers_and_a_nonce_based_script_policy(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]{20,}'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
