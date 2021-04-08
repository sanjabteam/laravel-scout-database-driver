<?php

namespace Sanjab\Tests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Sanjab\Tests\Models\Category;
use Sanjab\Tests\Models\Post;

class DatabaseEngineTest extends TestCase
{
    use RefreshDatabase;

    public function testSearchLike()
    {
        $category1 = Category::factory()->create(['name' => 'This is Hello World 1']);
        $category2 = Category::factory()->create(['name' => 'That is Hello World 2']);

        Post::factory()->create(['category_id' => $category1->id, 'name' => 'Post Number VeryRandomWordForTestOne', 'important' => true]);
        Post::factory()->create(['category_id' => $category1->id, 'name' => 'Post Number VeryRandomWordForTestTwo']);

        Post::factory()->create(['category_id' => $category2->id, 'name' => 'Post Number VeryRandomWordForTestThree', 'important' => true]);
        Post::factory()->create(['category_id' => $category2->id, 'name' => 'Post Number VeryRandomWordForTestFour']);

        $this->assertCount(2, Category::search('Hello World')->get());
        $this->assertCount(1, Category::search('THIS IS HELLO WORLD 1')->get());

        $this->assertCount(0, Category::search('That Hello World')->get());

        config(['scout.database.mode' => 'LIKE_EXPANDED']);
        $this->assertCount(2, Category::search('That Hello World')->get());
        config(['scout.database.mode' => 'LIKE']);

        $this->assertCount(4, Post::search('Hello World')->get());
        $this->assertCount(2, Post::search('Hello World 1')->get());

        $this->assertCount(4, Post::search('Post')->get());
        $this->assertCount(1, Post::search('VeryRandomWordForTestOne')->get());

        $this->assertCount(2, Post::search('Post')->where('important', 1)->get());
    }

    public function testUpdateAndDelete()
    {
        $this->assertCount(0, Category::search('Hello')->get());

        $category = Category::factory()->create(['name' => 'Hello World']);
        $this->assertCount(1, Category::search('Hello')->get());

        $category->update(['name' => 'Bye World']);
        $this->assertCount(0, Category::search('Hello')->get());
        $this->assertCount(1, Category::search('Bye')->get());

        $category->delete();
        $this->assertCount(0, Category::search('Hello')->get());
        $this->assertCount(0, Category::search('Bye')->get());
    }

    public function testPagination()
    {
        Category::factory()->create(['name' => 'VeryRandomWordForTest 1']);
        Category::factory()->create(['name' => 'VeryRandomWordForTest 2']);

        $this->assertCount(2, Category::search('VeryRandomWordForTest')->get());

        $categories = Category::search('VeryRandomWordForTest')->paginate(1, 'page', 1);
        $this->assertCount(1, $categories);
        $this->assertEquals(2, $categories->total());
        $categories = Category::search('VeryRandomWordForTest')->paginate(1, 'page', 2);
        $this->assertCount(1, $categories);
        $this->assertEquals(2, $categories->total());
        $categories = Category::search('VeryRandomWordForTest')->paginate(1, 'page', 3);
        $this->assertCount(0, $categories);
        $this->assertEquals(2, $categories->total());
    }

    public function testLimit()
    {
        Category::factory()->create(['name' => 'This is Hello World 1']);
        Category::factory()->create(['name' => 'That is Hello World 2']);

        $this->assertCount(2, Category::search('Hello World')->get());
        $this->assertCount(1, Category::search('Hello World')->take(1)->get());
    }

    public function testCallback()
    {
        Category::factory()->create(['name' => 'This is Hello World 1']);
        Category::factory()->create(['name' => 'That is Hello World 2']);

        $this->assertCount(
            2,
            Category::search('Hello World')->get()
        );
        $this->assertCount(
            1,
            Category::search('Hello World', function (Builder $query, string $search, array $options) {
                return $query->where('meta', 'LIKE', '%Hello World 2%');
            })
            ->get()
        );
    }

    public function testFlush()
    {
        Category::factory()->create(['name' => 'This is Hello World 1']);
        Category::factory()->create(['name' => 'That is Hello World 2']);

        $this->assertCount(2, Category::search('Hello World')->get());

        $this->artisan('scout:flush "'.str_replace('\\', '\\\\', Category::class).'"');

        $this->assertCount(0, Category::search('Hello World')->get());
    }

    public function testSoftDelete()
    {
        config(['scout.soft_delete' => true]);
        $category = Category::factory()->create(['name' => 'A Category']);

        Post::factory()->create(['category_id' => $category->id, 'name' => 'Post VeryRandomWordForTest the One']);
        $toDelete = Post::factory()->create(['category_id' => $category->id, 'name' => 'Post VeryRandomWordForTest the two']);
        $toDelete->delete();

        $this->assertCount(1, Post::search('VeryRandomWordForTest')->get());
        $this->assertCount(2, Post::search('VeryRandomWordForTest')->withTrashed()->get());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));

        // Test-only migrations
        $this->loadMigrationsFrom(realpath(__DIR__.'/database/migrations'));
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('scout', [
            'driver' => 'database',
            'prefix' => '',
            'queue' => false,
            'after_commit' => false,
            'chunk' => [
                'searchable' => 500,
                'unsearchable' => 500,
            ],
            'soft_delete' => false,
            'identify' => false,

            'database' => [
                'chunks' => 250,
            ],
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Scout\ScoutServiceProvider::class,
            \Sanjab\ScoutDatabaseDriverServiceProvider::class,
        ];
    }
}
