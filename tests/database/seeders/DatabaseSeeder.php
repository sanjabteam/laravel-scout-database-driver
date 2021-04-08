<?php

namespace Sanjab\Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Sanjab\Tests\Models\Category;
use Sanjab\Tests\Models\Post;
use Sanjab\Tests\Models\Tag;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Category::disableSearchSyncing();
        Post::disableSearchSyncing();
        Tag::disableSearchSyncing();

        $categories = collect();
        foreach (range(1, 5) as $range) {
            $category = Category::factory()->create(['name' => "Category number $range"]);

            Post::factory(rand(1, 5))
                ->has(Tag::factory(rand(1, 10)))
                ->create(['category_id' => $category->id]);

            $categories[] = $category;
        }

        Category::enableSearchSyncing();
        Post::enableSearchSyncing();
        Tag::enableSearchSyncing();

        Category::query()->searchable();
        Post::query()->searchable();
        Tag::query()->searchable();
    }
}
