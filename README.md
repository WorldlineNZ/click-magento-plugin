# Paymark payment module for Magento 2.x
This is a Paymark payment module for Magento 2. This module currently supports Paymark Click only.

Tested on Magento 2.3.x only.

## Installation

To install this module use the following composer command:

`composer require paymark/click`

Alternatively download the package and put the files into this folder in your Magento directory: `app/Onfire/Paymark`

After installing the files please run the following commands to enable the module:

```
#enable the module
php bin/magento module:enable Onfire_PaymarkClick

#run magento setup
php bin/magento setup:upgrade
```

## Config

You will need to register for Paymark Click before configuring this module. Visit www.paymark.co.nz for more info.

After the module has been installed go to `Stores > Settings > Configuration > Sales > Payment Methods` in the Magento Admin to find the configuration options.

The configuration options are as follows:

* Title: Title that will appear on the checkout page
* Click User ID: User ID for your click account
* Click Password: Password for your click account
* Click Account ID: Click account id (You can find this in your merchant console after registering)
* UAT: Flag to alternate UAT environment - this changes the payment URL
* Debug Log: Write logs to paymark.log during the checkout process for debugging purposes
* Payment Action: Option to either do an Authorisation charge or a full Capture (Paymark will need to enable the Authorisation option if you want to use this instead)

###### Note: if you only do an authorisation charge you will need to capture the full payment through the Paymark console.

