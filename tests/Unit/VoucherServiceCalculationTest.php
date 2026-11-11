<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Voucher;
use App\Services\VoucherService;
use PHPUnit\Framework\TestCase;

class VoucherServiceCalculationTest extends TestCase
{
    protected VoucherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VoucherService();
    }

    public function test_percentage_discount_calculation(): void
    {
        $voucher = new Voucher([
            'code' => 'DISKON10',
            'name' => 'Diskon 10%',
            'type' => Voucher::TYPE_PERCENTAGE,
            'amount' => 10,
            'min_spend' => 50000,
            'max_discount' => 20000,
            'is_active' => true,
        ]);

        $customer = new Customer(['id' => 1]);

        // Below min spend
        $resBelow = $this->service->validateAndCalculate($voucher, $customer, 40000, 15000);
        if ($resBelow['valid'] !== false) {
            throw new \Exception('Expected invalid below min spend');
        }

        // Eligible with cap (10% of 300.000 = 30.000, capped at 20.000)
        $resCapped = $this->service->validateAndCalculate($voucher, $customer, 300000, 15000);
        if ($resCapped['valid'] !== true || $resCapped['product_discount'] != 20000 || $resCapped['total_discount'] != 20000) {
            throw new \Exception('Failed capped percentage discount assertion: ' . json_encode($resCapped));
        }

        // Eligible without reaching cap (10% of 100.000 = 10.000)
        $resNormal = $this->service->validateAndCalculate($voucher, $customer, 100000, 15000);
        if ($resNormal['valid'] !== true || $resNormal['product_discount'] != 10000 || $resNormal['total_discount'] != 10000) {
            throw new \Exception('Failed normal percentage discount assertion: ' . json_encode($resNormal));
        }
    }

    public function test_fixed_discount_calculation(): void
    {
        $voucher = new Voucher([
            'code' => 'HEMAT25RB',
            'name' => 'Potongan 25k',
            'type' => Voucher::TYPE_FIXED,
            'amount' => 25000,
            'min_spend' => 50000,
            'is_active' => true,
        ]);

        $customer = new Customer(['id' => 1]);

        $res = $this->service->validateAndCalculate($voucher, $customer, 100000, 15000);
        if ($res['valid'] !== true || $res['product_discount'] != 25000 || $res['total_discount'] != 25000) {
            throw new \Exception('Failed fixed discount assertion: ' . json_encode($res));
        }
    }

    public function test_free_shipping_discount_calculation(): void
    {
        $voucher = new Voucher([
            'code' => 'GRATISONGKIR',
            'name' => 'Gratis Ongkir Maks 20k',
            'type' => Voucher::TYPE_FREE_SHIPPING,
            'amount' => 0,
            'max_discount' => 20000,
            'min_spend' => 0,
            'is_active' => true,
        ]);

        $customer = new Customer(['id' => 1]);

        // Shipping cost 30.000 -> capped at 20.000
        $res = $this->service->validateAndCalculate($voucher, $customer, 50000, 30000);
        if ($res['valid'] !== true || $res['shipping_discount'] != 20000 || $res['total_discount'] != 20000) {
            throw new \Exception('Failed free shipping cap assertion: ' . json_encode($res));
        }

        // Shipping cost 12.000 -> full discount 12.000
        $res2 = $this->service->validateAndCalculate($voucher, $customer, 50000, 12000);
        if ($res2['valid'] !== true || $res2['shipping_discount'] != 12000 || $res2['total_discount'] != 12000) {
            throw new \Exception('Failed free shipping exact assertion: ' . json_encode($res2));
        }
    }

    public function test_inactive_or_expired_voucher(): void
    {
        $inactive = new Voucher([
            'code' => 'OFF',
            'type' => Voucher::TYPE_FIXED,
            'amount' => 10000,
            'is_active' => false,
        ]);

        $customer = new Customer(['id' => 1]);
        $res = $this->service->validateAndCalculate($inactive, $customer, 50000, 10000);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('tidak aktif', $res['message']);

        $expired = new Voucher([
            'code' => 'OLD',
            'type' => Voucher::TYPE_FIXED,
            'amount' => 10000,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $resExpired = $this->service->validateAndCalculate($expired, $customer, 50000, 10000);
        $this->assertFalse($resExpired['valid']);
        $this->assertStringContainsString('kedaluwarsa', $resExpired['message']);
    }
}
