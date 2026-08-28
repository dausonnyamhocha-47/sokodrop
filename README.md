# 🛒 Sokodrop — Luxury Grocery & Commodity Concierge Platform

**Sokodrop** is a web-based luxury grocery and commodity concierge platform designed specifically for the Tanzanian market. It connects Kariakoo wholesale merchants in Dar es Salaam with VIP clients seeking private, high-value shopping experiences and discreet doorstep delivery.

---

## Core System Roles

1. **VIP Clients:** Browse premium catalogs anonymously, place orders with discreet checkout, and track real-time doorstep deliveries.
2. **Kariakoo Merchants:** Manage merchant portals, list retail/wholesale inventory, specify exact stall locations, and receive direct sales payouts.
3. **Shoppers / Concierge Agents:** Verified personal shoppers who pick up items from Kariakoo stalls, perform strict quality inspections, and handle final deliveries.
4. **Admins:** Oversee platform compliance, verify NIDA/KYC documentation, approve registered merchants/shoppers, and manage financial commission splits.

---

## 🛠️ Technology Stack

* **Backend Engine:** Laravel 11+ (RESTful API Architecture)
* **Authentication:** Laravel Sanctum (Token-Based Authentication & Role Authorization)
* **Frontend Application:** React.js + Vite + Tailwind CSS (Modular Single Page Application)
* **Database:** MySQL (Normalized to 3NF)
* **API Communication:** Axios Client with dynamic JWT Bearer authorization

---

## 📁 Complete Project File Structure

```text
sokodrop/
├── README.md                                     # System Architecture & Documentation
├── backend/                                      # Laravel RESTful API Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── Api/
│   │   │   │       ├── AuthController.php        # Authentication & Registration Engine
│   │   │   │       ├── VIP/
│   │   │   │       │   ├── ProductCatalogController.php
│   │   │   │       │   └── OrderController.php
│   │   │   │       ├── Merchant/
│   │   │   │       │   ├── ShopController.php
│   │   │   │       │   └── InventoryController.php
│   │   │   │       ├── Shopper/
│   │   │   │       │   └── DeliveryController.php
│   │   │   │       └── Admin/
│   │   │   │           └── VerificationController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── CheckRole.php
│   │   │   │   └── EnsureShopperIsVerified.php
│   │   │   └── Requests/
│   │   │       ├── StoreOrderRequest.php
│   │   │       └── RegisterShopperRequest.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── ShopperProfile.php
│   │   │   ├── Shop.php
│   │   │   ├── Product.php
│   │   │   ├── Order.php
│   │   │   ├── OrderItem.php
│   │   │   └── Transaction.php
│   │   └── Services/
│   │       └── CommissionCalculator.php          # Financial Split Logic Engine
│   ├── database/
│   │   └── migrations/
│   │       ├── 2026_01_01_000001_create_users_table.php
│   │       ├── 2026_01_01_000002_create_shopper_profiles_table.php
│   │       ├── 2026_01_01_000003_create_shops_table.php
│   │       ├── 2026_01_01_000004_create_products_table.php
│   │       ├── 2026_01_01_000005_create_orders_table.php
│   │       ├── 2026_01_01_000006_create_order_items_table.php
│   │       └── 2026_01_01_000007_create_transactions_table.php
│   └── routes/
│       └── api.php                               # Application REST API Endpoints
└── frontend/                                     # React.js + Vite Web Application
    └── src/
        ├── components/
        │   ├── common/
        │   │   ├── Navbar.jsx
        │   │   └── ProtectedRoute.jsx
        │   ├── vip/
        │   │   ├── ProductCard.jsx
        │   │   └── CartDrawer.jsx
        │   └── shopper/
        │       └── OrderCard.jsx
        ├── context/
        │   ├── AuthContext.jsx
        │   └── CartContext.jsx
        ├── pages/
        │   ├── auth/
        │   │   ├── Login.jsx
        │   │   └── RegisterShopper.jsx
        │   ├── vip/
        │   │   ├── Catalog.jsx
        │   │   └── Checkout.jsx
        │   ├── merchant/
        │   │   └── Inventory.jsx
        │   ├── shopper/
        │   │   └── Deliveries.jsx
        │   └── admin/
        │       └── Dashboard.jsx
        ├── services/
        │   ├── api.js
        │   └── authService.js
        ├── App.jsx
        └── main.jsx
