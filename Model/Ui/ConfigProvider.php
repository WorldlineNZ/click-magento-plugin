<?php

namespace Onfire\Paymark\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    private $_assetRepo;

    const CODE = 'paymark';

    /**
     * ConfigProvider constructor.
     *
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     */
    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo
    )
    {
        $this->_assetRepo = $assetRepo;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'logo' => $this->getClickLogo(),
                ]
            ]
        ];
    }

    /**
     * Get absolute path to the Online EFTPOS logo
     *
     * @return string
     */
    public function getClickLogo()
    {
        $url =  $this->_assetRepo->getUrl("Onfire_Paymark::images/logo.svg");;
        return $url;
    }
}
