<?php

namespace Sanjab\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Sanjab\Tests\Database\Factories\PostFactory;

/**
 * @property string $name
 * @property bool $important
 * @property int $category_id
 * @property null|string $description
 * @property null|string $content
 * @property-read \Sanjab\Tests\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection $tags
 */
class Post extends Model
{
    use Searchable, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'important',
        'category_id',
        'description',
        'content',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'important'   => 'bool',
        'category_id' => 'int',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Category of post.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Post tags.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'important'   => intval($this->important),
            'name'        => $this->name,
            'category'    => $this->category->name,
            'tags'        => $this->tags->pluck('name')->implode(','),
            'description' => $this->description,
            'content'     => $this->content,
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return new PostFactory();
    }
}
