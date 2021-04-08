<?php

namespace Sanjab\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sanjab\Tests\Models\Tag;

class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => implode(' ', $this->faker->words(rand(1, 3))),
        ];
    }
}
