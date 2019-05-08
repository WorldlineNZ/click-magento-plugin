<?php

namespace Onfire\Paymark\Api;

/**
 * @api
 */
interface ClickManagementInterface
{
    /**
     * Return redirect link
     *
     * @api
     * @return string
     */
    public function getRedirectLink();
}
