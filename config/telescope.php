<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    'enabled' => env('TELESCOPE_ENABLED', false),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION'),
        'queue' => env('TELESCOPE_QUEUE'),
        'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
    ],
    'middleware' => [
        'web',
        Authorize::class,
    ],
    'only_paths' => [],
    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        '_boost*',
    ],
    'ignore_commands' => [],
    'watchers' => [
        Watchers\BatchWatcher::class => false,
        Watchers\CacheWatcher::class => ['enabled' => false, 'hidden' => [], 'ignore' => []],
        Watchers\ClientRequestWatcher::class => false,
        Watchers\CommandWatcher::class => ['enabled' => false, 'ignore' => []],
        Watchers\DumpWatcher::class => ['enabled' => false, 'always' => false],
        Watchers\EventWatcher::class => ['enabled' => false, 'ignore' => []],
        Watchers\ExceptionWatcher::class => false,
        Watchers\GateWatcher::class => ['enabled' => false, 'ignore_abilities' => [], 'ignore_packages' => true, 'ignore_paths' => []],
        Watchers\JobWatcher::class => false,
        Watchers\LogWatcher::class => ['enabled' => false, 'level' => 'error'],
        Watchers\MailWatcher::class => false,
        Watchers\ModelWatcher::class => ['enabled' => false, 'events' => ['eloquent.*'], 'hydrations' => true],
        Watchers\NotificationWatcher::class => false,
        Watchers\QueryWatcher::class => ['enabled' => false, 'ignore_packages' => true, 'ignore_paths' => [], 'slow' => 100],
        Watchers\RedisWatcher::class => false,
        Watchers\RequestWatcher::class => ['enabled' => false, 'size_limit' => 64, 'ignore_http_methods' => [], 'ignore_status_codes' => []],
        Watchers\ScheduleWatcher::class => false,
        Watchers\ViewWatcher::class => false,
    ],
];
