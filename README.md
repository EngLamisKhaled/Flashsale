# ⚡ Flash Sale Checkout System (Laravel 12)

A backend system designed for **high-load flash sales**, focusing on:
- **Strong Concurrency Guarantees**
- **Overselling Protection**
- **Idempotent Payment Webhooks**
- **Short-lived Inventory Holds**
- **Correctness under race conditions**
- **Graceful handling of out-of-order events**

This project simulates a real-world flash-sale backend similar to:
Amazon Lightning Deals, Noon White Friday, Souq Flash Sales, Paymob/Stripe-based checkouts.

All logic is implemented using **Laravel 11**, **MySQL/InnoDB**, and **transaction-level locking**.

---

## 🧱 System Overview

The system sells a **single flash-sale product** with limited stock.

Key flow:
1. Users reserve stock using **holds**.
2. Holds last for 2 minutes.
3. Users create an **order** before the hold expires.
4. A payment provider sends a **webhook** confirming success/failure.
5. A **background job** expires unused holds.
6. **Idempotency keys** prevent duplicate webhook processing.
7. **Pessimistic locking** ensures safe concurrency.

---

## 🗂 Database Schema

### 1. `products`
- `id`, `name`, `price`
- `stock_total`
- `stock_sold`

Invariant:
```
available = stock_total - stock_sold - active_holds
```

---

### 2. `holds`
- `id`
- `product_id`
- `qty`
- `status`: `active`, `used`, `completed`, `expired`, `canceled`
- `expires_at`

Purpose:
- instantly reduces availability
- protects against overselling
- expires automatically in background

---

### 3. `orders`
- `id`
- `product_id`
- `hold_id`
- `qty`
- `total_price`
- `status`: `pending`, `paid`, `canceled`

---

### 4. `payment_events`
Stores idempotency information.

- `order_id`
- `idempotency_key` (unique)
- `status`
- `raw_payload`

Purpose:
- ensure "exactly-once" processing of webhooks

---

# 🔄 API Endpoints

---

## ✔ 1. Get Product Availability

`GET /api/products/{id}`

Example Response:
```json
{
  "id": 1,
  "name": "Flash Product",
  "price": 1000,
  "available_stock": 97
}
```

Algorithm:

-Load product.

-Compute active holds:

```
holds where status in ('active','used')
      and expires_at > now()
```

-Return:

```
available = stock_total - stock_sold - active_holds_sum
```

---

## ✔ 2. Create Hold  
`POST /api/holds`

Request:
```json
{
  "product_id": 1,
  "qty": 3
}
```

Response:
```json
{
  "hold_id": 10,
  "expires_at": "2025-12-02T22:10:00Z"
}
```

Guarantees:
- transaction-protected
- product row locked with `lockForUpdate()`
- prevents overselling under concurrency

---

## ✔ 3. Create Order  
`POST /api/orders`

Request:
```json
{
  "hold_id": 10
}
```

Rules:
- hold must be active & not expired
- hold becomes `used`
- order becomes `pending`

Response:
```json
{
  "order_id": 55,
  "status": "pending"
}
```

---

## ✔ 4. Payment Webhook (Idempotent + Safe)

`POST /api/payments/webhook`

Sample Body:
```json
{
  "order_id": 55,
  "status": "success",
  "idempotency_key": "evt_12345_xyz"
}
```

Logic:
1. Check idempotency key → if exists → return `"Already processed"`
2. Lock order, product, hold rows (`lockForUpdate`)
3. If `success`:
   - `order.status = paid`
   - `product.stock_sold += qty`
   - `hold.status = completed`
4. If `failure`:
   - `order.status = canceled`
   - `hold.status = canceled`
5. Store payment event

Guarantees:
- zero duplicate changes
- safe under retries
- safe under parallel webhook calls

---

# ⏳ Background Hold Expiration

Command:  
```
php artisan holds:expire
```

Scheduled in `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('holds:expire')->everyMinute();
```

Result:
- expired holds free stock automatically
- system stays correct without manual cleanup

---

# 🧠 Concurrency Model (Interview-Ready)

### ⭐ Pessimistic Locking
`lockForUpdate()` ensures:
- only one buyer can modify product/hold/order at a time
- prevents overselling under parallel load

### ⭐ Transactions
Everything critical runs in:
```php
DB::transaction(function () { ... });
```
Guarantees atomicity:
- all or nothing

### ⭐ Hold Reservation Model
Holds simulate “cart reservation”:
- valid for 2 minutes
- short-lived to avoid blocking stock

### ⭐ Webhook Idempotency
Same webhook → same idempotency_key → processed once only.

### ⭐ Out-of-Order Webhooks
If webhook arrives before order exists:
- handler returns 404
- provider retries
- system eventually processes it correctly

Documented as expected provider behavior.

### ⭐ Zero Overselling Guarantee
```
available = total - sold - active_holds
```
Combined with locking → impossible to oversell.

---

# 📦 Running the Project

```
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
php artisan schedule:work
```

---

# 🧪 Suggested Tests

### ✔ Parallel holds
Simulate 20 users → holds + sold never exceed limit.

### ✔ Hold expiry
Expired holds → status becomes `expired`.

### ✔ Webhook idempotency
Same webhook 10 times → processed once.

### ✔ Out-of-order webhook
Webhook before order → fail  
Order created → retry → success

### ✔ Concurrency race test
Two parallel webhook calls → only one updates.

---

# 🎯 Conclusion

A fully-correct flash-sale backend featuring:

- Concurrency-safe logic  
- Strong locking  
- Idempotent webhooks  
- Short-lived holds  
- Background expiry  
- Zero overselling  
- Clean API  
- Real-world correctness guarantees  

This architecture mirrors real production systems used by large-scale e-commerce platforms.
