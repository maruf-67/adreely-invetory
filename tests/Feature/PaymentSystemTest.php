<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Business;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\UserBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $business;
    protected $supplier;
    protected $product;
    protected $paymentMethod;
    protected $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create business
        $this->business = Business::factory()->create();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'user_type' => 'admin',
            'email' => 'admin@test.com',
        ]);
        
        // Create supplier
        $this->supplier = User::factory()->create([
            'business_id' => $this->business->id,
            'user_type' => 'supplier',
            'name' => 'Test Supplier',
            'current_balance' => 0,
        ]);
        
        // Create product
        $this->product = Product::factory()->create([
            'business_id' => $this->business->id,
        ]);
        
        // Create payment method
        $this->paymentMethod = PaymentMethod::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Cash',
            'type' => 'cash',
        ]);
        
        // Create purchase order
        $this->purchaseOrder = PurchaseOrder::create([
            'business_id' => $this->business->id,
            'supplier_id' => $this->supplier->id,
            'order_date' => now(),
            'status' => 'pending',
            'sub_total' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_normal_payment_without_overpayment()
    {
        $this->actingAs($this->admin);
        
        $response = $this->postJson("/api/purchase-orders/{$this->purchaseOrder->id}/payments", [
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 500,
            'transaction_date' => now()->toDateString(),
            'details' => 'Partial payment',
        ]);
        
        $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.overpayment_amount', 0);
        
        $this->purchaseOrder->refresh();
        $this->supplier->refresh();
        
        $this->assertEquals(500, $this->purchaseOrder->paid_amount);
        $this->assertEquals(0, $this->purchaseOrder->extra_amount);
        $this->assertEquals(0, $this->supplier->current_balance);
    }

    public function test_payment_with_overpayment()
    {
        $this->actingAs($this->admin);
        
        $response = $this->postJson("/api/purchase-orders/{$this->purchaseOrder->id}/payments", [
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 1200, // 200 overpayment
            'transaction_date' => now()->toDateString(),
            'details' => 'Full payment with overpayment',
        ]);
        
        $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.overpayment_amount', 200);
        
        $this->purchaseOrder->refresh();
        $this->supplier->refresh();
        
        $this->assertEquals(1200, $this->purchaseOrder->paid_amount);
        $this->assertEquals(200, $this->purchaseOrder->extra_amount);
        $this->assertEquals(200, $this->supplier->current_balance);
        
        // Check balance record was created
        $balanceRecord = UserBalance::where('user_id', $this->supplier->id)
                                  ->where('balance_type', 'credit')
                                  ->first();
        
        $this->assertNotNull($balanceRecord);
        $this->assertEquals(200, $balanceRecord->amount);
    }

    public function test_multiple_payments_with_overpayment()
    {
        $this->actingAs($this->admin);
        
        // First payment - partial
        $response1 = $this->postJson("/api/purchase-orders/{$this->purchaseOrder->id}/payments", [
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 800,
            'transaction_date' => now()->toDateString(),
            'details' => 'First payment',
        ]);
        
        $response1->assertStatus(200);
        
        // Second payment - overpayment
        $response2 = $this->postJson("/api/purchase-orders/{$this->purchaseOrder->id}/payments", [
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 300, // 100 overpayment
            'transaction_date' => now()->toDateString(),
            'details' => 'Second payment with overpayment',
        ]);
        
        $response2->assertStatus(200)
                 ->assertJsonPath('data.overpayment_amount', 100);
        
        $this->purchaseOrder->refresh();
        $this->supplier->refresh();
        
        $this->assertEquals(1100, $this->purchaseOrder->paid_amount);
        $this->assertEquals(100, $this->purchaseOrder->extra_amount);
        $this->assertEquals(100, $this->supplier->current_balance);
    }

    public function test_user_balance_summary()
    {
        // Add some balance to supplier
        $this->supplier->update(['current_balance' => 150]);
        
        $this->actingAs($this->admin);
        
        $response = $this->getJson('/api/user-balances/summary');
        
        $response->assertStatus(200)
                ->assertJsonPath('success', true);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('suppliers', $data);
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('summary', $data);
    }

    public function test_user_balance_history()
    {
        // Create balance record
        UserBalance::createRecord(
            $this->business->id,
            $this->supplier->id,
            'credit',
            100,
            'Test balance record'
        );
        
        $this->actingAs($this->admin);
        
        $response = $this->getJson("/api/user-balances/{$this->supplier->id}/history");
        
        $response->assertStatus(200)
                ->assertJsonPath('success', true);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, $data['data']);
    }

    public function test_balance_adjustment()
    {
        $this->actingAs($this->admin);
        
        $response = $this->postJson("/api/user-balances/{$this->supplier->id}/adjustment", [
            'amount' => 50,
            'balance_type' => 'credit',
            'description' => 'Manual adjustment',
            'reference_number' => 'ADJ-001',
        ]);
        
        $response->assertStatus(200)
                ->assertJsonPath('success', true);
        
        $this->supplier->refresh();
        $this->assertEquals(50, $this->supplier->current_balance);
        
        // Check balance record was created
        $balanceRecord = UserBalance::where('user_id', $this->supplier->id)
                                  ->where('description', 'Manual adjustment')
                                  ->first();
        
        $this->assertNotNull($balanceRecord);
        $this->assertEquals(50, $balanceRecord->amount);
    }
}
