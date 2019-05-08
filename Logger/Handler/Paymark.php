<?php
namespace Onfire\Paymark\Logger\Handler;

use \Magento\Framework\Logger\Handler\Base;
use \Monolog\Logger;

/**
 * Custom paymark logger handler
 */
class Paymark extends Base
{

    protected $fileName = '/var/log/paymark.log';

    protected $level = Logger::DEBUG;

}
