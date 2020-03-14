<?php
namespace YiluTech\YiMQ;
use Illuminate\Support\Facades\Facade;


/**
 * @method static \YiluTech\YiMQ\YiMqManager  client(string $name)
 * @method static \YiluTech\YiMQ\YiMqClient  mock()
 * @method static \YiluTech\YiMQ\YiMqClient  topic(string $topic)
 * @method static \YiluTech\YiMQ\YiMqClient  prepare()
 * @method static \YiluTech\YiMQ\YiMqClient  commit()
 * @method static \YiluTech\YiMQ\YiMqClient  rollback()
 * @method static \YiluTech\YiMQ\YiMqClient  tcc(string $processer)
 * @method static \YiluTech\YiMQ\YiMqClient  ec(string $processer)
 *
 */
class YiMqFacade extends Facade
{
    protected static function getFacadeAccessor() {
        return YiMqManager::class;
    }
}
