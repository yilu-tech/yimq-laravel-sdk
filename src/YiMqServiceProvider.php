<?php
namespace YiluTech\YiMQ;

use Illuminate\Support\ServiceProvider;

class YiMqServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->publishes([
            __DIR__ . '/../config/yimq.php' => config_path('yimq.php')
        ]);
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }


    public function register()
    {

        if(!class_exists('YiMQ')){
            class_alias(YiMqFacade::class,'YiMQ');
        }

        $this->app->singleton(YiMqManager::class, function ($app) {
            return new YiMqManager($app);
        });
    }


}