<?php

Route::prefix(config('yimq.route.prefix'))->name(config('yimq.route.name'))->group(function (){
    Route::post('yimq', 'YiluTech\YiMQ\Http\Controllers\YiMqController@run');
});
