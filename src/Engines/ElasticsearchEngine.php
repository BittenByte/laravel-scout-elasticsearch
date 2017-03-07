<?php

namespace BittenByte\ScoutElasticsearchEngine\Engines;

use Laravel\Scout\Builder;
use Elasticsearch\ClientBuilder;
use Laravel\Scout\Engines\Engine;
use Elasticsearch\Client as Elasticsearch;
use Illuminate\Database\Eloquent\Collection;

class ElasticsearchEngine extends Engine
{
    /**
     * The Elastic client.
     *
     * @var \ElasticSearch\Client
     */
    protected $client;

    /**
     * Create a new engine instance.
     *
     * @param array  $config 
     * 
     * @return void
     */
    public function __construct(array $config)
    {
        //Elastic 5.0...
        // https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_configuration.html
        $this->client = ClientBuilder::create()->setHosts($config['hosts'])->build();
    }


    /**
     * Initializes index 
     * @param string $index 
     */
    protected function initIndex(string $index)
    {
        if (!$this->client->indices()->exists(['index' => $index])) {   
            $this->client->indices()->create([
                'index' => $index,
                'body' => config("scout.elasticsearch.indices.$index", []),
            ]);
        }
    }
    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @throws \ElasticSearch\ElasticException
     * @return void
     */
    public function update($models)
    {
        $index = $models->first()->searchableAs();
        $this->initIndex($index);
        
        $models->map(function ($model) use ($index){
            $this->client->index([
                'index' => $index,
                'type' => $model->getType(),
                'id' => $model->getKey(),
                'body' => $model->toSearchableArray()
            ]);
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $index = $models->first()->searchableAs();
        $this->initIndex($index);

        $models->map(function ($model) use ($index){ 
            $this->client->delete([
                'index' => $index,
                'type' => $model->getType(),
                'id' => $model->getKey(),
            ]);
        });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $builder->limit,
        ]));
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
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $index = $builder->model->searchableAs();
        $this->initIndex($index);
        $fields = config("scout.elasticsearch.fields.$index", []);

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $elasticsearch,
                $builder->query,
                $options
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Default Searches
        |--------------------------------------------------------------------------
        |
        | Searches will depend on how the user is indexing data. By default, for
        | the "search" method that the engine has to implement, we understand that
        | we need to do a "partial match" across any indexed field. We sort it out
        | by one technique based on "not_analized" fields + wildard queries. We 
        | recommend to look a https://www.elastic.co/guide/en/elasticsearch/guide/current/partial-matching.html
        |  and tweak the index and query below.
        |
        */
        $params = [
            'index' => $index,
            'type' => $builder->model->getType(),
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => [
                            array_map(function ($field) use ($builder) {
                                return ['wildcard' => [$field => '*'.$builder->query.'*']];
                            }, $fields),
                        ],
                    ],
                ],
            ],
        ];

        //do this properly
        return $this->client->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            return $key.'='.$value;
        })->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map($results, $model)
    {

        if (count($results['hits']) === 0) {
            return Collection::make();
        }

        $keys = collect($results['hits']['hits'])
                        ->pluck('_id')->values()->all();

        $models = $model->whereIn(
            $model->getQualifiedKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

        return Collection::make($results['hits']['hits'])->map(function ($hit) use ($model, $models) {
            $key = $hit['_id'];

            if (isset($models[$key])) {
                return $models[$key];
            }
        })->filter();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }
}