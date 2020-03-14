<?php
namespace YiluTech\YiMQ;

use Illuminate\Support\ServiceProvider;

class YiMqServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->publishes([
            __DIR__ . '/../config/yimq.php' => config_path('yimq.php')
        ]);
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