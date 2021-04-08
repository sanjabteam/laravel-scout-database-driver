<?php

namespace Sanjab\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sanjab\Tests\Models\Post;

class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name'        => implode(' ', $this->faker->words(rand(1, 3))),
            'description' => $this->faker->sentence,
            'content'     => $this->faker->realText(10000),
        ];
    }
}
