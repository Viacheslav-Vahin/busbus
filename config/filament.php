<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Filament will be accessible from. Feel free
    | to change this path to anything you like.
    |
    */

    'path' => 'dashboard',  // Це шлях до адмін-панелі Filament

    'panels' => [
        'default' => [
            'id' => 'admin',
            'path' => 'dashboard',
            'resources' => [
                App\Filament\Resources\PostResource::class,
                App\Filament\Resources\UserResource::class,
                App\Filament\Resources\RoleResource::class,
                App\Filament\Resources\PermissionResource::class,
                App\Filament\Resources\BusResource::class,
                App\Filament\Resources\StopResource::class,
                App\Filament\Resources\BookingResource::class,
            ],
            'pages' => [
                App\Filament\Pages\Dashboard::class,
            ],
            'widgets' => [
                App\Filament\Widgets\AccountWidget::class,
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Filament Core Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Filament's core assets are stored. You may
    | change this path if you want to serve these assets from a different
    | location.
    |
    */

    'core_path' => 'filament',

    /*
    |--------------------------------------------------------------------------
    | Filament Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Filament will be accessible from. If the
    | setting is null, Filament will reside under the same domain as the
    | app. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This is the storage disk Filament will use to store files. You may use
    | any of the disks defined in the `config/filesystems.php`.
    |
    */

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Filament will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan filament:cache-components`.
    |
    */

    'cache_path' => base_path('bootstrap/cache/filament'),

    'locale' => 'uk',

    /*
    |--------------------------------------------------------------------------
    | Auth Guard
    |--------------------------------------------------------------------------
    |
    | Filament will use this guard to authenticate users. If you have multiple
    | guards, you may specify which one should be used.
    |
    */

    'auth' => [
        'guard' => env('FILAMENT_AUTH_GUARD', 'web'),
        'middleware' => ['web', 'auth'],  // Додайте додаткові middleware, якщо потрібно
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | By uncommenting the Laravel Echo configuration, you may connect Filament
    | to any Pusher-compatible websockets server.
    |
    | This will allow your users to receive real-time notifications.
    |
    */

    'broadcasting' => [

        // 'echo' => [
        //     'broadcaster' => 'pusher',
        //     'key' => env('VITE_PUSHER_APP_KEY'),
        //     'cluster' => env('VITE_PUSHER_APP_CLUSTER'),
        //     'wsHost' => env('VITE_PUSHER_HOST'),
        //     'wsPort' => env('VITE_PUSHER_PORT'),
        //     'wssPort' => env('VITE_PUSHER_PORT'),
        //     'authEndpoint' => '/broadcasting/auth',
        //     'disableStats' => true,
        //     'encrypted' => true,
        //     'forceTLS' => true,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the model that Filament will use for user authentication and
    | authorization. You may change this to your own user model.
    |
    */

    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Livewire Loading Delay
    |--------------------------------------------------------------------------
    |
    | This sets the delay before loading indicators appear.
    |
    | Setting this to 'none' makes indicators appear immediately, which can be
    | desirable for high-latency connections. Setting it to 'default' applies
    | Livewire's standard 200ms delay.
    |
    */

    'livewire_loading_delay' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Layouts
    |--------------------------------------------------------------------------
    |
    | Here you may configure the layouts that are available for your Filament
    | panels.
    |
    */

    'layouts' => [
        'app' => App\Filament\Layouts\AppLayout::class,
        'panel' => App\Filament\Layouts\PanelLayout::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    |
    | You may specify the default theme that should be used for your Filament
    | panels.
    |
    */

    'themes' => [
        'default' => 'light', // Вкажіть 'dark' для темної теми
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Directory
    |--------------------------------------------------------------------------
    |
    | This is the directory where Filament will store generated resources.
    |
    */

    'resources_directory' => app_path('Filament/Resources'),

    'layouts_directory' => app_path('Filament/Layouts'),

    'layout' => [
        'sidebar' => [
            'is_collapsible_on_desktop' => true,
        ],
    ],

    'forms' => [
        'default' => [
            'input' => [
                'class' => 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm',
            ],
            'select' => [
                'class' => 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm',
            ],
            'textarea' => [
                'class' => 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm',
            ],
            'checkbox' => [
                'class' => 'text-indigo-600 border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm',
            ],
            'radio' => [
                'class' => 'text-indigo-600 border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm',
            ],
            'button' => [
                'class' => 'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500',
            ],
        ],
    ],

    'table' => [
        'default' => [
            'class' => 'min-w-full divide-y divide-gray-200',
            'th' => [
                'class' => 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider',
            ],
            'td' => [
                'class' => 'px-6 py-4 whitespace-nowrap text-sm text-gray-500',
            ],
            'tr' => [
                'class' => 'bg-white',
            ],
            'tr_even' => [
                'class' => 'bg-gray-50',
            ],
            'tr_odd' => [
                'class' => 'bg-white',
            ],
            'tr_selected' => [
                'class' => 'bg-indigo-50',
            ],
            'tr_hover' => [
                'class' => 'hover:bg-gray-100',
            ],
            'tr_active' => [
                'class' => 'active:bg-gray-200',
            ],
            'tr_focus' => [
                'class' => 'focus:bg-gray-200',
            ],
            'tr_group' => [
                'class' => 'group hover:bg-gray-50',
            ],
            'tr_group_even' => [
                'class' => 'group even:bg-gray-50',
            ],
            'tr_group_odd' => [
                'class' => 'group odd:bg-white',
            ],
            'tr_group_selected' => [
                'class' => 'group selected:bg-indigo-50',
            ],
            'tr_group_hover' => [
                'class' => 'group hover:bg-gray-100',
            ],
            'tr_group_active' => [
                'class' => 'group active:bg-gray-200',
            ],
            'tr_group_focus' => [
                'class' => 'group focus:bg-gray-200',
            ],
            'th_sortable' => [
                'class' => 'cursor-pointer',
            ],
            'th_sortable_asc' => [
                'class' => 'text-indigo-500',
            ],
            'th_sortable_desc' => [
                'class' => 'text-indigo-500',
            ],
            'th_sortable_focus' => [
                'class' => 'focus:outline-none focus:ring focus:ring-indigo-500 focus:ring-opacity-50',
            ],
            'th_sortable_hover' => [
                'class' => 'hover:text-indigo-700',
            ],
            'th_sortable_active' => [
                'class' => 'active:text-indigo-700',
            ],
            'th_sortable_group' => [
                'class' => 'group',
            ],
            'th_sortable_group_asc' => [
                'class' => 'group text-indigo-500',
            ],
        ]
    ],

    'pagination' => [
        'default' => [
            'class' => 'border-t border-gray-200 px-4 py-3 flex items-center justify-between sm:px-6',
            'label' => [
                'class' => 'text-sm text-gray-700',
            ],
            'link' => [
                'class' => 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50',
            ],
            'link_disabled' => [
                'class' => 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 cursor-not-allowed',
            ],
            'link_active' => [
                'class' => 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-600 text-sm font-medium text-white',
            ],
            'link_focus' => [
                'class' => 'focus:z-10 focus:outline-none focus:ring ring-gray-300',
            ],
            'link_hover' => [
                'class' => 'hover:bg-gray-50',
            ],
            'link_active_hover' => [
                'class' => 'hover:bg-indigo-700',
            ],
            'link_disabled_hover' => [
                'class' => 'hover:bg-white',
            ],
            'link_group' => [
                'class' => 'group',
            ],
            'link_group_hover' => [
                'class' => 'group hover:bg-gray-50',
            ],
            'link_group_active' => [
                'class' => 'group active:bg-gray-100',
            ],
            'link_group_focus' => [
                'class' => 'group focus:bg-gray-100',
            ],
            'link_group_active_hover' => [
                'class' => 'group hover:bg-indigo-700',
            ],
            'link_group_disabled' => [
                'class' => 'group cursor-not-allowed',
            ],
            'link_group_disabled_hover' => [
                'class' => 'group hover:bg-white',
            ],
            'link_group_focus' => [
                'class' => 'group focus:bg-gray-100',
            ],
            'link_group_focus_hover' => [
                'class' => 'group hover:bg-gray-100',
            ],
            'link_group_focus_active' => [
                'class' => 'group active:bg-gray-100',
            ],
            'link_group_focus_active_hover' => [
                'class' => 'group hover:bg-indigo-700',
            ],
        ]
    ],

//    'pages' => [
//        'register' => [
//            App\Filament\Pages\CreateBooking::class,
//        ],
//    ],

];
