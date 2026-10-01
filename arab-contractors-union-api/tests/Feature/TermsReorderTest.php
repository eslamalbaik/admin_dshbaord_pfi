<?php

namespace Tests\Feature;

use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TermsReorderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function seedFour(): array
    {
        return [
            'A' => Term::create(['type' => 'terms', 'title' => 'A', 'body' => 'x', 'sort' => 0]),
            'B' => Term::create(['type' => 'terms', 'title' => 'B', 'body' => 'x', 'sort' => 1]),
            'C' => Term::create(['type' => 'terms', 'title' => 'C', 'body' => 'x', 'sort' => 2]),
            'D' => Term::create(['type' => 'terms', 'title' => 'D', 'body' => 'x', 'sort' => 3]),
        ];
    }

    private function sortsByTitle(): array
    {
        return Term::orderBy('sort')->pluck('sort', 'title')->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Mirrors TermsManager.vue's moveUp/moveDown: PUT the moved item with
    //  the *neighbor's current sort value* — not a designated "swap" endpoint.
    // ─────────────────────────────────────────────────────────────────────

    public function test_move_down_swaps_with_next_sibling(): void
    {
        $this->actingAsAdmin();
        $t = $this->seedFour();

        // moveDown(index 1 = B): next = C (sort 2) → PUT B with sort=2
        $this->putJson("/api/v1/dashboard/terms/{$t['B']->id}", [
            'type' => 'terms', 'title' => 'B', 'body' => 'x', 'sort' => 2, 'is_active' => true,
        ])->assertStatus(200);

        $this->assertSame(['A' => 0, 'C' => 1, 'B' => 2, 'D' => 3], $this->sortsByTitle());
    }

    public function test_move_up_swaps_with_previous_sibling(): void
    {
        $this->actingAsAdmin();
        $t = $this->seedFour();

        // moveUp(index 2 = C): prev = B (sort 1) → PUT C with sort=1
        $this->putJson("/api/v1/dashboard/terms/{$t['C']->id}", [
            'type' => 'terms', 'title' => 'C', 'body' => 'x', 'sort' => 1, 'is_active' => true,
        ])->assertStatus(200);

        $this->assertSame(['A' => 0, 'C' => 1, 'B' => 2, 'D' => 3], $this->sortsByTitle());
    }

    public function test_move_first_item_down_to_last_position(): void
    {
        $this->actingAsAdmin();
        $t = $this->seedFour();

        // repeated moveDown on A: A(0)<->B(1), then A(1)<->C(2), then A(2)<->D(3)
        $this->putJson("/api/v1/dashboard/terms/{$t['A']->id}", ['type' => 'terms', 'title' => 'A', 'body' => 'x', 'sort' => 1, 'is_active' => true]);
        $this->assertSame(['B' => 0, 'A' => 1, 'C' => 2, 'D' => 3], $this->sortsByTitle());

        $this->putJson("/api/v1/dashboard/terms/{$t['A']->id}", ['type' => 'terms', 'title' => 'A', 'body' => 'x', 'sort' => 2, 'is_active' => true]);
        $this->assertSame(['B' => 0, 'C' => 1, 'A' => 2, 'D' => 3], $this->sortsByTitle());

        $this->putJson("/api/v1/dashboard/terms/{$t['A']->id}", ['type' => 'terms', 'title' => 'A', 'body' => 'x', 'sort' => 3, 'is_active' => true]);
        $this->assertSame(['B' => 0, 'C' => 1, 'D' => 2, 'A' => 3], $this->sortsByTitle());
    }

    public function test_reorder_within_one_type_does_not_affect_the_other_type(): void
    {
        $this->actingAsAdmin();
        $t = $this->seedFour();
        $p1 = Term::create(['type' => 'privacy', 'title' => 'P1', 'body' => 'x', 'sort' => 0]);
        $p2 = Term::create(['type' => 'privacy', 'title' => 'P2', 'body' => 'x', 'sort' => 1]);

        $this->putJson("/api/v1/dashboard/terms/{$t['B']->id}", [
            'type' => 'terms', 'title' => 'B', 'body' => 'x', 'sort' => 2, 'is_active' => true,
        ])->assertStatus(200);

        $this->assertSame(0, $p1->fresh()->sort);
        $this->assertSame(1, $p2->fresh()->sort);
    }

    public function test_store_inserts_at_position_and_shifts_following_items(): void
    {
        $this->actingAsAdmin();
        $this->seedFour();

        $this->postJson('/api/v1/dashboard/terms', [
            'type' => 'terms', 'title' => 'NEW', 'body' => 'x', 'sort' => 1, 'is_active' => true,
        ])->assertStatus(201);

        $this->assertSame(['A' => 0, 'NEW' => 1, 'B' => 2, 'C' => 3, 'D' => 4], $this->sortsByTitle());
    }
}
