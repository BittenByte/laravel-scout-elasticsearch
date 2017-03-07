# Laravel Scout ElasticSearch engine

An ElasticSearch Custom Engine implementation for Laravel Scout (3.*)

*IMPORTANT* this is still an experimental package!!!

## Instalation

Install the package via composer: 

```
composer require bittenbyte/laravel-scout-elasticsearch
```

Add the service provider to the providers section in the config/app.php (of course you need Scout too)

```
    ...
    BittenByte\ScoutElasticsearchEngine\ScoutElasticsearchEngineServiceProvider::class,
    ...
``` 

In the config/scout.php set the driver properly and add a key as per below:

```
    ...
    'driver' => 'elasticsearch',
    ...
    'elasticsearch' => [
        'config' => [
            'hosts' => array_map('trim', explode(',', env('ELASTICSEARCH_HOSTS', 'localhost'))),
        ],
        //default searchable fields per index
        'fields' => [
            'users_index' => [
                'name', 'email', 'role', 'slug',
            ],
        ],
        'indices' => [
            //set your OPTIONAL index specific settings and mappings
            'users_index' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2
                ],
                'mappings' => [
                    'authenticable' => [
                        '_source' => [
                            'enabled' => true,
                        ],
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'index' => 'not_analyzed',
                            ],
                            'slug' => [
                                'type' => 'string',
                                'index' => 'not_analyzed',
                            ],
                            'role' => [
                                'type' => 'string',
                                'index' => 'not_analyzed',
                            ],
                            'email' => [
                                'type' => 'string',
                                'index' => 'not_analyzed',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
```

## Usage

Follow this documentation below and the official docs for scout

## TO-DO (docs) 

Advanced/Custom query builder

## Documentation

Not to forget from Scout docs

indexation is automaticaly hooked to Eloquent create, update and (soft)delete operations

Also is possible to index/remove from index manually

```
// Updating via Eloquent query...
App\Order::where('price', '>', 100)->searchable();

// You may also update via relationships...
$user->orders()->searchable();

// You may also update via collections...
$orders->searchable();
```

```
// Removing via Eloquent query...
App\Order::where('price', '>', 100)->unsearchable();

// You may also remove via relationships...
$user->orders()->unsearchable();

// You may also remove via collections...
$orders->unsearchable();
```

### Pausing Indexing

This is useful specially when doing batch operations

```
App\Order::withoutSyncingToSearch(function () {
    // Perform model actions...
});
```

### Search (try out in tinker)
```
$orders = App\Order::search('Star Trek')->get();
```

### Where clauses (only id). Weird concept (NoSQL and sql wheres?)
```
$orders = App\Order::search('Star Trek')->where('user_id', 1)->get();
```

### Pagination
```
$orders = App\Order::search('Star Trek')->paginate();
```
