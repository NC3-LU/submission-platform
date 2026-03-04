<?php

namespace Database\Factories;

use App\Models\ScanResult;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanResult>
 */
class ScanResultFactory extends Factory
{
    protected $model = ScanResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'submission_value_id' => $this->faker->randomNumber(),
            'is_malicious' => false,
            'scan_results' => ['status' => 'CLEAN', 'taskId' => $this->faker->uuid()],
            'scanner_used' => 'pandora',
            'filename' => $this->faker->word() . '.pdf',
        ];
    }

    /**
     * Indicate that the scan result is malicious.
     */
    public function malicious(): static
    {
        return $this->state(fn () => [
            'is_malicious' => true,
            'scan_results' => ['status' => 'ALERT', 'taskId' => $this->faker->uuid()],
        ]);
    }
}
