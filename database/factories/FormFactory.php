<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'header_image' => null,
            'header_image_position' => 50,
            'header_theme_color' => null,
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'visibility' => $this->faker->randomElement(['public', 'authenticated', 'private']),
        ];
    }

    /**
     * Indicate that the form is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    /**
     * Indicate that the form is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the form is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the form is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Attach a header image to the form.
     */
    public function withHeaderImage(string $path = 'form-headers/test.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'header_image' => $path,
            'header_theme_color' => '#3366cc',
            'header_image_position' => 60,
        ]);
    }
}
