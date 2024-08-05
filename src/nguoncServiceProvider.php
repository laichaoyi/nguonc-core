<?php

namespace nguonc\Core;

use Illuminate\Console\Scheduling\Schedule;
use nguonc\Core\Policies\PermissionPolicy;
use nguonc\Core\Policies\RolePolicy;
use nguonc\Core\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use nguonc\Core\Console\CreateUser;
use nguonc\Core\Console\InstallCommand;
use nguonc\Core\Console\GenerateMenuCommand;
use nguonc\Core\Console\ChangeDomainEpisodeCommand;
use nguonc\Core\Middleware\CKFinderAuth;
use nguonc\Core\Models\Actor;
use nguonc\Core\Models\Catalog;
use nguonc\Core\Models\Category;
use nguonc\Core\Models\Director;
use nguonc\Core\Models\Episode;
use nguonc\Core\Models\Menu;
use nguonc\Core\Models\Movie;
use nguonc\Core\Models\Region;
use nguonc\Core\Models\Studio;
use nguonc\Core\Models\Tag;
use nguonc\Core\Models\Theme;
use nguonc\Core\Policies\ActorPolicy;
use nguonc\Core\Policies\CatalogPolicy;
use nguonc\Core\Policies\CategoryPolicy;
use nguonc\Core\Policies\CrawlSchedulePolicy;
use nguonc\Core\Policies\DirectorPolicy;
use nguonc\Core\Policies\EpisodePolicy;
use nguonc\Core\Policies\MenuPolicy;
use nguonc\Core\Policies\MoviePolicy;
use nguonc\Core\Policies\RegionPolicy;
use nguonc\Core\Policies\StudioPolicy;
use nguonc\Core\Policies\TagPolicy;

class nguoncServiceProvider extends ServiceProvider
{
    /**
     * Get the policies defined on the provider.
     *
     * @return array
     */
    public function policies()
    {
        return [
            Actor::class => ActorPolicy::class,
            Catalog::class => CatalogPolicy::class,
            Category::class => CategoryPolicy::class,
            Region::class => RegionPolicy::class,
            Director::class => DirectorPolicy::class,
            Tag::class => TagPolicy::class,
            Studio::class => StudioPolicy::class,
            Movie::class => MoviePolicy::class,
            Episode::class => EpisodePolicy::class,
            Menu::class => MenuPolicy::class,
            CrawlSchedule::class => CrawlSchedulePolicy::class
        ];
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'nguonc');

        $this->mergeBackpackConfigs();

        $this->mergeCkfinderConfigs();

        $this->mergePolicies();
    }

    public function boot()
    {
        $this->registerPolicies();

        try {
            foreach (glob(__DIR__ . '/Helpers/*.php') as $filename) {
                require_once $filename;
            }
        } catch (\Exception $e) {
            //throw $e;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

        $this->app->booted(function () {
            $this->loadThemeRoutes();
            $this->loadScheduler();
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views/core/', 'nguonc');

        $this->loadViewsFrom(__DIR__ . '/../resources/views/themes', 'themes');

        $this->publishFiles();

        $this->commands([
            InstallCommand::class,
            CreateUser::class,
            GenerateMenuCommand::class,
            ChangeDomainEpisodeCommand::class,
        ]);

        $this->bootSeoDefaults();
    }

    protected function publishFiles()
    {
        $backpack_menu_contents_view = [
            __DIR__ . '/../resources/views/core/base/'  => resource_path('views/vendor/hacoidev/base/'),
            __DIR__ . '/../resources/views/core/crud/'      => resource_path('views/vendor/hacoidev/crud/'),
        ];

        $players = [
            __DIR__ . '/../resources/assets/js/hls.min.js' => public_path('js/hls.min.js'),
            __DIR__ . '/../resources/assets/js/jwplayer-8.9.3.js' => public_path('js/jwplayer-8.9.3.js'),
            __DIR__ . '/../resources/assets/js/jwplayer.hlsjs.min.js' => public_path('js/jwplayer.hlsjs.min.js'),
        ];

        $this->publishes($backpack_menu_contents_view, 'cms_menu_content');
        $this->publishes($players, 'players');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('nguonc.php')
        ], 'config');
    }

    protected function mergeBackpackConfigs()
    {
        config(['backpack.base.styles' => array_merge(config('backpack.base.styles', []), [
            'packages/select2/dist/css/select2.css',
            'packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css'
        ])]);

        config(['backpack.base.scripts' => array_merge(config('backpack.base.scripts', []), [
            'packages/select2/dist/js/select2.full.min.js'
        ])]);

        config(['backpack.base.middleware_class' => array_merge(config('backpack.base.middleware_class', []), [
            \Backpack\CRUD\app\Http\Middleware\UseBackpackAuthGuardInsteadOfDefaultAuthGuard::class,
        ])]);

        config(['cachebusting_string' => \PackageVersions\Versions::getVersion('hacoidev/crud')]);

        config(['backpack.base.project_logo' => '<b>LOGO</b>']);
        config(['backpack.base.developer_name' => 'Nguyễn Đức Độ']);
        config(['backpack.base.developer_link' => 'mailto:nguyenducdo009@gmail.com']);
        config(['backpack.base.show_powered_by' => false]);
    }

    protected function mergeCkfinderConfigs()
    {
        config(['ckfinder.authentication' => CKFinderAuth::class]);
        config(['ckfinder.backends.default' => config('nguonc.ckfinder.backends')]);
    }

    protected function mergePolicies()
    {
        config(['backpack.permissionmanager.policies.permission' => PermissionPolicy::class]);
        config(['backpack.permissionmanager.policies.role' => RolePolicy::class]);
        config(['backpack.permissionmanager.policies.user' => UserPolicy::class]);
    }

    protected function bootSeoDefaults()
    {
        config([
            'seotools.meta.defaults.title' => setting('site_homepage_title'),
            'seotools.meta.defaults.description' => setting('site_meta_description'),
            'seotools.meta.defaults.keywords' => [setting('site_meta_keywords')],
            'seotools.meta.defaults.canonical' => url("/")
        ]);

        config([
            'seotools.opengraph.defaults.title' => setting('site_homepage_title'),
            'seotools.opengraph.defaults.description' => setting('site_meta_description'),
            'seotools.opengraph.defaults.type' => 'website',
            'seotools.opengraph.defaults.url' => url("/"),
            'seotools.opengraph.defaults.site_name' => setting('site_meta_siteName'),
            'seotools.opengraph.defaults.images' => [setting('site_meta_image')],
        ]);

        config([
            'seotools.twitter.defaults.card' => 'website',
            'seotools.twitter.defaults.title' => setting('site_homepage_title'),
            'seotools.twitter.defaults.description' => setting('site_meta_description'),
            'seotools.twitter.defaults.url' => url("/"),
            'seotools.twitter.defaults.site' => setting('site_meta_siteName'),
            'seotools.twitter.defaults.image' => setting('site_meta_image'),
        ]);

        config([
            'seotools.json-ld.defaults.title' => setting('site_homepage_title'),
            'seotools.json-ld.defaults.type' => 'WebPage',
            'seotools.json-ld.defaults.description' => setting('site_meta_description'),
            'seotools.json-ld.defaults.images' => setting('site_meta_image'),
        ]);
    }

    protected function loadThemeRoutes()
    {
        try {
            $activatedTheme = Theme::getActivatedTheme();
            if ($activatedTheme && file_exists($routeFile = base_path('vendor/' . $activatedTheme->package_name . '/routes/web.php'))) {
                $this->loadRoutesFrom($routeFile);
            }
        } catch (\Exception $e) {
            // Log
        }
    }

    protected function loadScheduler()
    {
        $schedule = $this->app->make(Schedule::class);

        $schedule->call(function () {
            DB::table('movies')->update(['view_day' => 0]);
        })->daily();
        $schedule->call(function () {
            DB::table('movies')->update(['view_week' => 0]);
        })->weekly();
        $schedule->call(function () {
            DB::table('movies')->update(['view_month' => 0]);
        })->monthly();
    }
}