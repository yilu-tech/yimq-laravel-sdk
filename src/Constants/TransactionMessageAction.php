<?php


namespace YiluTech\YiMQ\Constants;


class TransactionMessageAction
{
    const BEGIN = 'BEGIN';
    CONST PREPARE = 'PREPARE';
    CONST COMMIT = 'COMMIT';
    CONST ROLLBACK = 'ROLLBACK';
}