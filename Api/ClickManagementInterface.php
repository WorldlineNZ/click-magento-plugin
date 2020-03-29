<?php

namespace Onfire\PaymarkClick\Api;

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
