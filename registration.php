<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Module registration file
 * This file registers the module with Magento's component registrar
 * Enables Magento to recognize and load the module during initialization
 */

use Magento\Framework\Component\ComponentRegistrar;

// Register the module with Magento's component system
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'TheDevKitchen_JwtCrossDomainAuth',
    __DIR__
);
