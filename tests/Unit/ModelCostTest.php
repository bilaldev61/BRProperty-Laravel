<?php

namespace Tests\Unit;

use App\Support\Ai\ModelCost;
use Tests\TestCase;

class ModelCostTest extends TestCase
{
    public function test_a_free_slug_costs_nothing(): void
    {
        $this->assertSame(
            ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
            ModelCost::rates('nvidia/nemotron-3-ultra-550b-a55b:free'),
        );
        $this->assertSame(0.0, ModelCost::estimate('nvidia/nemotron-3-ultra-550b-a55b:free', 5000, 0, 0, 300));
    }

    public function test_a_model_with_dots_in_its_slug_is_priced(): void
    {
        // config() dot notation would split "5.5"; the map lookup must not.
        $this->assertNotNull(ModelCost::rates('anthropic/claude-haiku-5.5'));
    }

    public function test_an_unknown_model_is_unpriced_rather_than_zero(): void
    {
        $this->assertNull(ModelCost::rates('some/unknown-model'));
        $this->assertNull(ModelCost::estimate('some/unknown-model', 5000, 0, 0, 300));
    }

    public function test_the_estimate_matches_openrouter_s_actual_haiku_bill(): void
    {
        // The five measured probe requests, priced exactly as OpenRouter billed them.
        $this->assertEqualsWithDelta(0.000490, ModelCost::estimate('anthropic/claude-haiku-5.5', 2977, 0, 2973, 236), 0.000001);
        $this->assertEqualsWithDelta(0.000170, ModelCost::estimate('anthropic/claude-haiku-5.5', 3264, 2973, 287, 208), 0.000001);
        $this->assertEqualsWithDelta(0.000217, ModelCost::estimate('anthropic/claude-haiku-5.5', 3769, 3260, 507, 242), 0.000001);
        $this->assertEqualsWithDelta(0.000164, ModelCost::estimate('anthropic/claude-haiku-5.5', 3536, 3260, 272, 194), 0.000001);
        $this->assertEqualsWithDelta(0.000154, ModelCost::estimate('anthropic/claude-haiku-5.5', 3757, 3532, 221, 181), 0.000001);
    }

    public function test_reading_from_cache_is_cheaper_than_sending_it_fresh(): void
    {
        $fresh = ModelCost::estimate('anthropic/claude-haiku-5.5', 3000, 0, 0, 100);
        $cached = ModelCost::estimate('anthropic/claude-haiku-5.5', 3000, 2900, 0, 100);

        $this->assertLessThan($fresh, $cached);
    }

    public function test_formatting(): void
    {
        $this->assertSame('—', ModelCost::format(null));
        $this->assertSame('$0.00', ModelCost::format(0.0));
        $this->assertSame('$0.0012', ModelCost::format(0.001195));
        $this->assertSame('$1.50', ModelCost::format(1.5));
    }

    public function test_a_model_is_categorised_for_the_panel(): void
    {
        config(['ai.local_models' => ['qwen3-14b']]);

        $this->assertSame('local', ModelCost::category('qwen3-14b'));
        $this->assertSame('free', ModelCost::category('nvidia/nemotron-3-ultra-550b-a55b:free'));
        $this->assertSame('paid', ModelCost::category('anthropic/claude-haiku-5.5'));
        // An unknown cloud slug is paid, not local: it is not in the local list.
        $this->assertSame('paid', ModelCost::category('some/unknown-model'));
    }

    public function test_a_local_model_costs_nothing(): void
    {
        config(['ai.local_models' => ['qwen3-14b']]);

        $this->assertSame(
            ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
            ModelCost::rates('qwen3-14b'),
        );
        $this->assertSame(0.0, ModelCost::estimate('qwen3-14b', 5000, 0, 0, 300));
    }
}
