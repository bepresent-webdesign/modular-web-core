<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Exceptions\CatalogException;

final class ProductCatalog
{
    private const REQUIRED_KEYS = [
        'product_id',
        'name',
        'description',
        'license_type',
        'max_downloads',
        'includes_updates',
        'update_months',
        'intended_zip',
        'upgrade_from',
        'upgrade_to',
        'price_net_eur',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $products;

    /** @var array<string, array<string, mixed>> */
    private array $licenseTypes;

    public function __construct(string $productsPath, string $licenseTypesPath)
    {
        $this->licenseTypes = $this->loadLicenseTypes($licenseTypesPath);
        $this->products = $this->loadAndValidateProducts($productsPath);
    }

    /**
     * @return array<string, mixed> Product record
     * @throws CatalogException
     */
    public function get(string $productId): array
    {
        if (!isset($this->products[$productId])) {
            throw new CatalogException("Product not found: {$productId}");
        }

        return $this->products[$productId];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->products;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadLicenseTypes(string $path): array
    {
        if (!is_file($path)) {
            throw new CatalogException("License types config not found: {$path}");
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new CatalogException("License types config must return an array");
        }

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadAndValidateProducts(string $path): array
    {
        if (!is_file($path)) {
            throw new CatalogException("Products config not found: {$path}");
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new CatalogException("Products config must return an array");
        }

        $products = [];
        foreach ($data as $key => $product) {
            if (!is_array($product)) {
                throw new CatalogException("Product entry must be an array: {$key}");
            }

            $validated = $this->validateProduct($product, (string) $key);
            $products[$validated['product_id']] = $validated;
        }

        return $products;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function validateProduct(array $product, string $key): array
    {
        foreach (self::REQUIRED_KEYS as $required) {
            if (!array_key_exists($required, $product)) {
                throw new CatalogException("Product {$key}: missing required key '{$required}'");
            }
        }

        $productId = $product['product_id'];
        if (!is_string($productId) || $productId === '') {
            throw new CatalogException("Product {$key}: product_id must be a non-empty string");
        }
        if ($productId !== $key) {
            throw new CatalogException("Product {$key}: product_id '{$productId}' must match array key");
        }

        $licenseType = $product['license_type'];
        if (!is_string($licenseType) || $licenseType === '') {
            throw new CatalogException("Product {$key}: license_type must be a non-empty string");
        }
        if (!isset($this->licenseTypes[$licenseType])) {
            throw new CatalogException("Product {$key}: unknown license_type '{$licenseType}'");
        }

        $maxDownloads = $product['max_downloads'];
        if (!is_int($maxDownloads) || $maxDownloads < 1) {
            throw new CatalogException("Product {$key}: max_downloads must be an integer >= 1");
        }

        $includesUpdates = $product['includes_updates'];
        if (!is_bool($includesUpdates)) {
            throw new CatalogException("Product {$key}: includes_updates must be a boolean");
        }

        $updateMonths = $product['update_months'];
        if (!is_int($updateMonths) || $updateMonths < 0) {
            throw new CatalogException("Product {$key}: update_months must be a non-negative integer");
        }

        if ($includesUpdates && $updateMonths === 0) {
            throw new CatalogException("Product {$key}: includes_updates=true requires update_months > 0");
        }
        if (!$includesUpdates && $updateMonths !== 0) {
            throw new CatalogException("Product {$key}: includes_updates=false requires update_months=0");
        }

        $upgradeFrom = $product['upgrade_from'];
        if ($upgradeFrom !== null && (!is_string($upgradeFrom) || $upgradeFrom === '')) {
            throw new CatalogException("Product {$key}: upgrade_from must be null or non-empty string");
        }

        $upgradeTo = $product['upgrade_to'];
        if ($upgradeTo !== null && (!is_string($upgradeTo) || $upgradeTo === '')) {
            throw new CatalogException("Product {$key}: upgrade_to must be null or non-empty string");
        }

        $priceNetEur = $product['price_net_eur'];
        if (!is_int($priceNetEur) || $priceNetEur < 0) {
            throw new CatalogException("Product {$key}: price_net_eur must be a non-negative integer (cents)");
        }

        $intendedZip = $product['intended_zip'];
        if (!is_string($intendedZip) || $intendedZip === '') {
            throw new CatalogException("Product {$key}: intended_zip must be a non-empty string");
        }

        return $product;
    }
}
