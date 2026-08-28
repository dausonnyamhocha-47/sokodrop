<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopperProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Database\Seeder;

/**
 * DemoDataSeeder
 *
 * Populates one full, realistic slice of the Sokodrop domain — a merchant
 * with an approved Kariakoo shop and products, an approved shopper, a VIP
 * client, and one completed order with its transaction — so the frontend
 * (Catalog.jsx, Inventory.jsx, Deliveries.jsx, Admin\Dashboard.jsx) has
 * real data to render right after a fresh `migrate:fresh --seed`, without
 * needing Faker/factories for a first pass. All money math for the sample
 * order goes through CommissionCalculator, same as production code paths,
 * so the seeded data is a valid worked example of the payout split.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $commissionCalculator = app(CommissionCalculator::class);

        // ------------------------------------------------------------
        // Merchant + approved shop
        // ------------------------------------------------------------
        $merchant = User::firstOrCreate(
            ['email' => 'merchant@sokodrop.co.tz'],
            [
                'name' => 'Juma Mwinyimvua',
                'phone' => '+255711000001',
                'password' => 'Password123!',
                'role' => User::ROLE_MERCHANT,
                'nida_number' => 'NIDA-MER-0001',
                'is_verified' => true,
            ]
        );

        $shop = Shop::firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'shop_name' => 'Mwinyimvua General Store',
                'stall_number' => 'K-142',
                'market_section' => 'Mchikichini',
                'description' => 'Bidhaa za jumla na rejareja — nafaka, viungo, na vifaa vya nyumbani.',
                'is_approved' => true,
                'is_active' => true,
            ]
        );

        // ------------------------------------------------------------
        // Products
        // ------------------------------------------------------------
        $products = [
            ['name' => 'Mchele Kilo 1 (Kyela)', 'category' => 'Grains', 'price' => 3500, 'unit' => 'kg', 'stock_quantity' => 200],
            ['name' => 'Unga wa Ngano Kilo 2', 'category' => 'Grains', 'price' => 4200, 'unit' => 'kg', 'stock_quantity' => 150],
            ['name' => 'Mafuta ya Kupikia Lita 1', 'category' => 'Cooking Essentials', 'price' => 6500, 'unit' => 'litre', 'stock_quantity' => 100],
            ['name' => 'Sukari Kilo 1', 'category' => 'Grocery', 'price' => 3200, 'unit' => 'kg', 'stock_quantity' => 180],
            ['name' => 'Chumvi Iodized 500g', 'category' => 'Spices', 'price' => 800, 'unit' => 'piece', 'stock_quantity' => 300],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => $productData['name']],
                [...$productData, 'is_active' => true]
            );
        }

        // ------------------------------------------------------------
        // Approved shopper
        // ------------------------------------------------------------
        $shopper = User::firstOrCreate(
            ['email' => 'shopper@sokodrop.co.tz'],
            [
                'name' => 'Amina Rajabu',
                'phone' => '+255711000002',
                'password' => 'Password123!',
                'role' => User::ROLE_SHOPPER,
                'nida_number' => 'NIDA-SHP-0001',
                'is_verified' => true,
            ]
        );

        $shopperProfile = ShopperProfile::firstOrCreate(
            ['user_id' => $shopper->id],
            [
                'id_number' => 'ID-SHP-0001',
                'vehicle_type' => 'motorcycle',
                'verification_status' => ShopperProfile::STATUS_APPROVED,
                'rating' => 4.85,
                'completed_deliveries' => 12,
                'is_available' => true,
            ]
        );

        // ------------------------------------------------------------
        // VIP client
        // ------------------------------------------------------------
        $vip = User::firstOrCreate(
            ['email' => 'vip@sokodrop.co.tz'],
            [
                'name' => 'Client VIP-001', // Discreet display name, per platform's anonymity model
                'phone' => '+255711000003',
                'password' => 'Password123!',
                'role' => User::ROLE_VIP,
                'is_verified' => true,
            ]
        );

        // ------------------------------------------------------------
        // One sample completed order, walking through the same lifecycle
        // and money math as the real API (VIP\OrderController::store ->
        // Shopper\DeliveryController::complete -> PaymentService).
        // ------------------------------------------------------------
        if (! Order::where('client_id', $vip->id)->exists()) {
            $rice = Product::where('shop_id', $shop->id)->where('name', 'Mchele Kilo 1 (Kyela)')->first();
            $oil = Product::where('shop_id', $shop->id)->where('name', 'Mafuta ya Kupikia Lita 1')->first();

            $order = Order::create([
                'client_id' => $vip->id,
                'shopper_id' => $shopper->id,
                'status' => Order::STATUS_COMPLETED,
                'delivery_address' => 'Masaki, Dar es Salaam (Gate 4, discreet drop-off)',
                'delivery_notes' => 'Piga simu ukiwa nje ya geti — usibonye kengele.',
                'accepted_at' => now()->subHours(3),
                'delivered_at' => now()->subMinutes(20),
            ]);

            foreach ([[$rice, 3], [$oil, 2]] as [$product, $qty]) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => round((float) $product->price * $qty, 2),
                    'quality_checked' => true,
                ]);
            }

            // Same CommissionCalculator path used in production — the
            // seeded order is a real, arithmetically valid worked example.
            $commissionCalculator->applyToOrder($order, 5000.00);

            $transaction = Transaction::create([
                'order_id' => $order->id,
                'payment_method' => 'mobile_money',
                'payment_gateway' => 'M-Pesa',
                'payment_status' => Transaction::STATUS_PAID,
                'paid_at' => now()->subMinutes(15),
            ]);
            $commissionCalculator->recordTransactionPayout($order->fresh(), $transaction);

            $shopperProfile->increment('completed_deliveries');
        }
    }
}<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopperProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Database\Seeder;

/**
 * DemoDataSeeder
 *
 * Populates one full, realistic slice of the Sokodrop domain — a merchant
 * with an approved Kariakoo shop and products, an approved shopper, a VIP
 * client, and one completed order with its transaction — so the frontend
 * (Catalog.jsx, Inventory.jsx, Deliveries.jsx, Admin\Dashboard.jsx) has
 * real data to render right after a fresh `migrate:fresh --seed`, without
 * needing Faker/factories for a first pass. All money math for the sample
 * order goes through CommissionCalculator, same as production code paths,
 * so the seeded data is a valid worked example of the payout split.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $commissionCalculator = app(CommissionCalculator::class);

        // ------------------------------------------------------------
        // Merchant + approved shop
        // ------------------------------------------------------------
        $merchant = User::firstOrCreate(
            ['email' => 'merchant@sokodrop.co.tz'],
            [
                'name' => 'Juma Mwinyimvua',
                'phone' => '+255711000001',
                'password' => 'Password123!',
                'role' => User::ROLE_MERCHANT,
                'nida_number' => 'NIDA-MER-0001',
                'is_verified' => true,
            ]
        );

        $shop = Shop::firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'shop_name' => 'Mwinyimvua General Store',
                'stall_number' => 'K-142',
                'market_section' => 'Mchikichini',
                'description' => 'Bidhaa za jumla na rejareja — nafaka, viungo, na vifaa vya nyumbani.',
                'is_approved' => true,
                'is_active' => true,
            ]
        );

        // ------------------------------------------------------------
        // Products
        // ------------------------------------------------------------
        $products = [
            ['name' => 'Mchele Kilo 1 (Kyela)', 'category' => 'Grains', 'price' => 3500, 'unit' => 'kg', 'stock_quantity' => 200],
            ['name' => 'Unga wa Ngano Kilo 2', 'category' => 'Grains', 'price' => 4200, 'unit' => 'kg', 'stock_quantity' => 150],
            ['name' => 'Mafuta ya Kupikia Lita 1', 'category' => 'Cooking Essentials', 'price' => 6500, 'unit' => 'litre', 'stock_quantity' => 100],
            ['name' => 'Sukari Kilo 1', 'category' => 'Grocery', 'price' => 3200, 'unit' => 'kg', 'stock_quantity' => 180],
            ['name' => 'Chumvi Iodized 500g', 'category' => 'Spices', 'price' => 800, 'unit' => 'piece', 'stock_quantity' => 300],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => $productData['name']],
                [...$productData, 'is_active' => true]
            );
        }

        // ------------------------------------------------------------
        // Approved shopper
        // ------------------------------------------------------------
        $shopper = User::firstOrCreate(
            ['email' => 'shopper@sokodrop.co.tz'],
            [
                'name' => 'Amina Rajabu',
                'phone' => '+255711000002',
                'password' => 'Password123!',
                'role' => User::ROLE_SHOPPER,
                'nida_number' => 'NIDA-SHP-0001',
                'is_verified' => true,
            ]
        );

        $shopperProfile = ShopperProfile::firstOrCreate(
            ['user_id' => $shopper->id],
            [
                'id_number' => 'ID-SHP-0001',
                'vehicle_type' => 'motorcycle',
                'verification_status' => ShopperProfile::STATUS_APPROVED,
                'rating' => 4.85,
                'completed_deliveries' => 12,
                'is_available' => true,
            ]
        );

        // ------------------------------------------------------------
        // VIP client
        // ------------------------------------------------------------
        $vip = User::firstOrCreate(
            ['email' => 'vip@sokodrop.co.tz'],
            [
                'name' => 'Client VIP-001', // Discreet display name, per platform's anonymity model
                'phone' => '+255711000003',
                'password' => 'Password123!',
                'role' => User::ROLE_VIP,
                'is_verified' => true,
            ]
        );

        // ------------------------------------------------------------
        // One sample completed order, walking through the same lifecycle
        // and money math as the real API (VIP\OrderController::store ->
        // Shopper\DeliveryController::complete -> PaymentService).
        // ------------------------------------------------------------
        if (! Order::where('client_id', $vip->id)->exists()) {
            $rice = Product::where('shop_id', $shop->id)->where('name', 'Mchele Kilo 1 (Kyela)')->first();
            $oil = Product::where('shop_id', $shop->id)->where('name', 'Mafuta ya Kupikia Lita 1')->first();

            $order = Order::create([
                'client_id' => $vip->id,
                'shopper_id' => $shopper->id,
                'status' => Order::STATUS_COMPLETED,
                'delivery_address' => 'Masaki, Dar es Salaam (Gate 4, discreet drop-off)',
                'delivery_notes' => 'Piga simu ukiwa nje ya geti — usibonye kengele.',
                'accepted_at' => now()->subHours(3),
                'delivered_at' => now()->subMinutes(20),
            ]);

            foreach ([[$rice, 3], [$oil, 2]] as [$product, $qty]) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => round((float) $product->price * $qty, 2),
                    'quality_checked' => true,
                ]);
            }

            // Same CommissionCalculator path used in production — the
            // seeded order is a real, arithmetically valid worked example.
            $commissionCalculator->applyToOrder($order, 5000.00);

            $transaction = Transaction::create([
                'order_id' => $order->id,
                'payment_method' => 'mobile_money',
                'payment_gateway' => 'M-Pesa',
                'payment_status' => Transaction::STATUS_PAID,
                'paid_at' => now()->subMinutes(15),
            ]);
            $commissionCalculator->recordTransactionPayout($order->fresh(), $transaction);

            $shopperProfile->increment('completed_deliveries');
        }
    }
}