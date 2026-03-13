<?php
namespace Paymark\PaymarkClick\Logger\Handler;

use \Magento\Framework\Logger\Handler\Base;
use Monolog\Level;

/**
 * Custom paymark logger handler
 */
class Paymark extends Base
{

    protected $fileName = '/var/log/paymark.log';

    protected Level $level = Level::Debug;

}
