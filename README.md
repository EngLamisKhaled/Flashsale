# ⚡ Flash Sale Checkout System (Laravel 11)
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
To prevent overselling during heavy concurrency:

1. Users **reserve (hold)** stock for a short window (e.g. 2 minutes).  
2. Users must create an **Order** before the hold expires.  
3. A **payment provider** sends a webhook confirming success/failure.  
4. A **scheduled background job** expires unused holds.
5. **Idempotency keys** prevent duplicate processing of the same webhook.
6. **Pessimistic database locking (`SELECT … FOR UPDATE`)** ensures correctness under parallel traffic.

---

## 🗂 Database Schema

### **1. `products`**
| Field | Type | Description |
|-------|--------|----------------|
| id | PK | Product ID |
| name | string | Product name |
| price | int | Price in EGP |
| stock_total | int | Total units allocated for flash sale |
| stock_sold | int | Units already sold (confirmed) |

**Invariant:**  
`available_stock = stock_total - stock_sold - active_holds`

---

### **2. `holds`** (short-lived reservations)

| Field | Type | Description |
|--------|--------|----------------|
| id | PK | Hold ID |
| product_id | FK | Related product |
| qty | int | Reserved quantity |
| status | enum | `active`, `used`, `completed`, `expired`, `canceled` |
| expires_at | timestamp | Expiry time |
| created_at | timestamp | — |

**Behavior:**
- Immediately reduces stock availability.  
- Used only once to create an order.  
- Auto-expires in background.

---

### **3. `orders`**

| Field | Type | Description |
|--------|--------|----------------|
| id | PK |
| product_id | FK |
| hold_id | FK |
| qty | int |
| total_price | int |
| status | enum | `pending`, `paid`, `canceled` |

---

### **4. `payment_events`** (Idempotency storage)

| Field | Type | Description |
|--------|--------|----------------|
| id | PK |
| order_id | FK |
| idempotency_key | string (unique) |
| status | string (`success/failure`) |
| raw_payload | json | Full webhook body |

---

## 🔄 API Endpoints

### ✔ **1. Get Product Availability**  
`GET /api/products/{id}`

Returns:
```json
{
  "id": 1,
  "name": "Flash Product",
  "price": 1000,
  "available_stock": 97
}
Algorithm:

-Load product.

-Compute active holds:

holds where status in ('active','used')
      and expires_at > now()

-Return:

available = stock_total - stock_sold - active_holds_sum

