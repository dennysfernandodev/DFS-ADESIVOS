<?php
return [
    'version' => '2.7.0',
    'pgcache.enabled' => true,
    'pgcache.engine' => 'file_generic',
    'pgcache.cache.query' => false,
    'pgcache.reject.front_page' => false,
    'pgcache.reject.logged' => true,
    'pgcache.reject.uri' => [
        '/cart/',
        '/checkout/',
        '/minha-conta/',
        '/my-account/',
    ],
    'objectcache.enabled' => true,
    'objectcache.engine' => 'redis',
    'objectcache.redis.servers' => [ 'redis:6379' ],
    'fragmentcache.enabled' => false,
    'browsercache.enabled' => true,
    'browsercache.cache.control' => true,
    'browsercache.cache.policy' => 'cache_public_maxage',
    'browsercache.expires.ttl' => 31536000,
    'minify.enabled' => false,
];
