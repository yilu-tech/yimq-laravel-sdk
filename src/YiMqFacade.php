<?php
namespace YiluTech\YiMQ;
use Illuminate\Support\Facades\Facade;


/**
 * @method static \YiluTech\YiMQ\YiMqManager  client(string $name)
 * @method static \YiluTech\YiMQ\Mock\YiMqMockerBuilder  mock()
 * @method static \YiluTech\YiMQ\Message\TransactionMessage  transaction(string $topic,$callback)
 * @method static \YiluTech\YiMQ\Message\TransactionMessage  commit()
 * @method static \YiluTech\YiMQ\Message\TransactionMessage  rollback()
 * @method static \YiluTech\YiMQ\Subtask\TccSubtask  tcc(string $processor)
 * @method static \YiluTech\YiMQ\Subtask\EcSubtask  ec(string $processor)
 * @method static \YiluTech\YiMQ\Subtask\XaSubtask  xa(string $processor)
 * @method static \YiluTech\YiMQ\Subtask\BcstSubtask  bcst(string $topic)
 * @method static \YiluTech\YiMQ\YiMqClient  clearTransactionMessage()
 *
 */
class YiMqFacade extends Facade
{
    protected static function getFacadeAccessor() {
        return YiMqManager::class;
    }
}
