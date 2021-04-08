<?php

namespace Sanjab;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class DatabaseEngine extends Engine
{
    /**
     * Database manager.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $db;

    /**
     * Create a new engine instance.
     *
     * @param  \Illuminate\Database\DatabaseManager  $database
     * @param  bool  $softDelete
     * @return void
     */
    public function __construct(DatabaseManager $database)
    {
        $this->db = $database;
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     *
     * @throws \Algolia\AlgoliaSearch\Exceptions\AlgoliaException
     */
    public function update($models)
    {
        // @codeCoverageIgnoreStart
        if ($models->isEmpty()) {
            return;
        }
        // @codeCoverageIgnoreEnd

        if ($this->usesSoftDelete($models->first()) && config('scout.soft_delete')) {
            $models->each->pushSoftDeleteMetadata();
        }

        $models = $models->filter(function ($model) {
            return empty($model->toSearchableArray()) == false;
        });

        $allWords = $models->map(function ($model) {
            return array_map(function ($word) {
                return [
                    'word' => $word,
                ];
            }, $this->getSearchableWords($model->toSearchableArray()));
        })->flatten(1)->unique('word');

        foreach ($allWords->chunk($this->getChunks()) as $allWordsChunked) {
            $this->wordsTable()->upsert(
                $allWordsChunked->toArray(),
                ['word']
            );
        }

        $type = $models->first()->searchableAs();

        $models->chunk($this->getChunks())->each(function ($models) use ($type) {
            $objects = $models->map(function ($model) use ($type) {
                return [
                    'object_id'  => $model->getScoutKey(),
                    'type'       => $type,
                    'meta'       => json_encode($model->toSearchableArray()),
                    'scout_meta' => empty($model->scoutMetadata()) ? null : json_encode($model->scoutMetadata()),
                ];
            })->filter()->values();

            $this->objectsTable()->upsert(
                $objects->toArray(),
                ['object_id', 'type'],
                ['meta', 'scout_meta']
            );

            $relations = $this->objectsTable()
                ->where('type', $type)
                ->whereIn('object_id', $objects->pluck('object_id')->toArray())
                ->get()
                ->map(function ($object) {
                    $wordsData = collect($this->getSearchableWords(json_decode($object->meta, true), true));
                    $fullWords = $wordsData->where('full', true)->pluck('word')->toArray();
                    $objectWords = $this->wordsTable()->whereIn('word', $wordsData->pluck('word')->toArray())->get();

                    return $objectWords->map(function ($objectWord) use ($object, $fullWords) {
                        return [
                            'searchable_object_id' => $object->id,
                            'searchable_word_id'   => $objectWord->id,
                            'full'                 => in_array($objectWord->word, $fullWords),
                        ];
                    });
                })->flatten(1);
            $relationsObjectIds = $relations->map(function ($relation) {
                return $relation['searchable_object_id'];
            })->unique()->toArray();
            $relationsWordIds = $relations->map(function ($relation) {
                return $relation['searchable_word_id'];
            })->unique()->toArray();

            foreach ($relations->chunk($this->getChunks()) as $relationsChunked) {
                $this->pivotTable()->upsert(
                    $relationsChunked->toArray(),
                    ['searchable_object_id', 'searchable_word_id'],
                    ['full']
                );
            }
            $relationsWordIdsCount = count($relationsWordIds);
            foreach (array_chunk($relationsObjectIds, max(5, $this->getChunks() - $relationsWordIdsCount)) as $relationsObjectIdsChunked) {
                $this->pivotTable()
                    ->whereIn('searchable_object_id', $relationsObjectIdsChunked)
                    ->whereNotIn('searchable_word_id', $relationsWordIds)
                    ->delete();
            }
        });
        // $this->removeUnusedWords();
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $ids = $models->map(function ($model) {
            return $model->getScoutKey();
        })->values()->toArray();

        $type = $models->first()->searchableAs();
        $this->objectsTable()
                    ->where('type', $type)
                    ->whereIn('object_id', $ids)
                    ->delete();

        $this->removeUnusedWords();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        $searchResults = $this->performSearch($builder)->get();

        return [
            'ids' => $searchResults->pluck('object_id')->toArray(),
            'total' => $searchResults->count(),
        ];
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $searchQuery = $this->performSearch($builder);
        $countQuery = $this->performSearch($builder, ['count' => true]);

        return [
            'ids' => $searchQuery->forPage($page, $perPage)->get()->pluck('object_id')->toArray(),
            'total' => $countQuery->count(),
        ];
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $options
     * @return \Illuminate\Database\Query\Builder
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $count = Arr::get($options, 'count', false);
        $relevance = Arr::get($options, 'relevance', config('scout.database.relevance', 5));
        $relevanceQuery = $this->getRelevanceQuery(mb_strtolower($builder->query), $options);
        $query = $this->objectsTable()
                ->where('type', $builder->index ?: $builder->model->searchableAs())
                ->join('sanjab_searchable_object_words', 'sanjab_searchable_object_words.searchable_object_id', '=', 'sanjab_searchable_objects.id')
                ->join('sanjab_searchable_words', 'sanjab_searchable_words.id', '=', 'sanjab_searchable_object_words.searchable_word_id')
                ->groupBy('sanjab_searchable_objects.object_id');

        if ($count) {
            $query->select('sanjab_searchable_objects.object_id')
                ->havingRaw('max('.$relevanceQuery['query'].') >= ?', array_merge($relevanceQuery['bindings'], [$relevance]));
        } else {
            $query->selectRaw(
                    'sanjab_searchable_objects.object_id, max('.$relevanceQuery['query'].') as relevance',
                    $relevanceQuery['bindings']
                )
                ->having('relevance', '>=', $relevance)
                ->orderBy('relevance', 'desc');
        }

        foreach ($builder->wheres as $column => $value) {
            $search = preg_replace('/^\{|\}$/', '%', json_encode([$column => $value]));

            $query->where(function ($query) use ($search) {
                $query->where('sanjab_searchable_objects.meta', 'like', $search)
                    ->orWhere('sanjab_searchable_objects.scout_meta', 'like', $search);
            });
        }

        if ($builder->limit > 0) {
            $query->limit($builder->limit);
        }

        if ($builder->callback) {
            $query = call_user_func(
                $builder->callback,
                $query,
                $builder->query,
                $options
            );
        }

        if ($count) {
            return $this->getConnection()->query()->fromRaw('('.$query->toSql().') as COUNT', $query->getBindings());
        }

        return $query;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     *
     * @codeCoverageIgnore
     */
    public function mapIds($results)
    {
        return $results['ids'];
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results['ids']) === 0) {
            return $model->newCollection();
        }

        $objectIds = $results['ids'];
        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
                $builder, $objectIds
            )->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $type = $model->searchableAs();
        $this->objectsTable()
                    ->where('type', $type)
                    ->delete();
        $this->removeUnusedWords();
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Delete all words without objects from words table.
     *
     * @return int
     */
    protected function removeUnusedWords()
    {
        return $this->wordsTable()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sanjab_searchable_objects')
                    ->join('sanjab_searchable_object_words', 'sanjab_searchable_objects.id', '=', 'sanjab_searchable_object_words.searchable_object_id')
                    ->whereColumn('sanjab_searchable_object_words.searchable_word_id', 'sanjab_searchable_words.id');
            })
            ->delete();
    }

    /**
     * Generate relevance query.
     *
     * @param string  $search
     * @param  array  $options
     * @return array
     */
    public function getRelevanceQuery(string $search, array $options = [])
    {
        $out = [];

        $mode = Arr::get($options, 'mode', config('scout.database.mode', 'LIKE'));
        $like = $this->isPostgresql() ? 'ILIKE' : 'LIKE';
        $wordColumn = $this->getConnection()->getQueryGrammar()->wrap('word');

        $searchableWords = $this->getSearchableWords([$search], true);

        switch ($mode) {
            case 'LIKE_EXPANDED':
                foreach ($searchableWords as $searchableWord) {
                    $out[] = ['query' => $this->getCaseQuery("$wordColumn $like ?", 20 * ($searchableWord['full'] ? 5 : 1)), 'binding' => $searchableWord['word']];
                    $out[] = ['query' => $this->getCaseQuery("$wordColumn $like ?", 5 * ($searchableWord['full'] ? 3 : 1)), 'binding' => $searchableWord['word'].'%'];
                    $out[] = ['query' => $this->getCaseQuery("$wordColumn $like ?", 1 * ($searchableWord['full'] ? 2 : 1)), 'binding' => '%'.$searchableWord['word'].'%'];
                }
                break;

            default: // LIKE
                $out[] = ['query' => $this->getCaseQuery("$wordColumn $like ?", 100), 'binding' => '%'.$search.'%'];
                break;
        }

        return [
            'query' => "\n".implode(" + \n", array_map(function ($o) {
                return $o['query'];
            }, $out)),
            'bindings' => array_map(function ($o) {
                return $o['binding'];
            }, $out),
        ];
    }

    /**
     * Generate case query.
     *
     * @param string $statement
     * @param int $relevance
     * @return void
     */
    protected function getCaseQuery(string $statement, int $relevance = 1)
    {
        $fullColumn = $this->getConnection()->getQueryGrammar()->wrap('sanjab_searchable_object_words.full');

        return "(case when $statement then (case when $fullColumn = 1 then $relevance * 2 else $relevance end) else 0 end)";
    }

    /**
     * Convert scout searchable array to words only.
     *
     * @param array $searchableArray
     * @param bool $asArray  set this to true to get each item as array with informations
     * @return array
     */
    protected function getSearchableWords(array $searchableArray, bool $asArray = false)
    {
        $words = collect();
        foreach ($searchableArray as $value) {
            if (empty($value)) {
                continue;
            }
            if (mb_strlen($value) <= 191) {
                $words[] = $asArray ? ['word' => mb_strtolower($value), 'full' => true] : mb_strtolower($value);
            }
            $valueExploded = explode(' ', preg_replace('/[\n\s!@#$%^&*,.;:~`\'"|_+\-*\[\]{}\(\)\\/<>]+/', ' ', $value));
            if (count($valueExploded) > 1) {
                foreach ($valueExploded as $ev) {
                    if (! empty($ev)) {
                        $words[] = $asArray ? ['word' => mb_strtolower($ev), 'full' => false] : mb_strtolower($ev);
                    }
                }
            }
        }
        if ($asArray) {
            $words = $words->unique('word');
        } else {
            $words = $words->unique();
        }

        return $words->toArray();
    }

    /**
     * Get default database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        return $this->db->connection(config('scout.database.connection'));
    }

    /**
     * Get database table.
     *
     * @param string $table
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getTable(string $table)
    {
        return $this->getConnection()->table($table);
    }

    /**
     * Get query builder for objects table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function objectsTable()
    {
        return $this->getTable('sanjab_searchable_objects');
    }

    /**
     * Get query builder for words table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function wordsTable()
    {
        return $this->getTable('sanjab_searchable_words');
    }

    /**
     * Get chunks config value.
     *
     * @return int
     */
    protected function getChunks()
    {
        return config('scout.database.chunks', 65535);
    }

    /**
     * Get query builder for pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function pivotTable()
    {
        return $this->getTable('sanjab_searchable_object_words');
    }

    /**
     * Is connection a postgress connection.
     *
     * @return bool
     */
    protected function isPostgresql()
    {
        return $this->getConnection() instanceof \Illuminate\Database\PostgresConnection;
    }
}
