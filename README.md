# Ysabelle Store Platform

Enterprise-grade Laravel 12 retail and ecommerce platform built for modern storefront operations, intelligent inventory management, operational reporting, AI-assisted product discovery, and unified retail workflows.

Ysabelle Store combines a polished ecommerce storefront, enterprise admin control center, realtime operational tooling, stock intelligence, walk-in POS functionality, and AI-powered shopping assistance into a single integrated retail ecosystem.

---

# Platform Overview

The platform was engineered to simulate a production-grade retail environment with unified operational management across ecommerce, inventory, reporting, customer management, and intelligent product discovery workflows.

Core platform capabilities include:

- Enterprise ecommerce storefront
- Admin control center
- AI-powered shopping assistant
- Image-based product recognition
- Unified inventory management
- Walk-in POS operations
- Operational reporting and exports
- Batch inventory import system
- Realtime operational monitoring
- Audit logging infrastructure
- Enterprise validation and security workflows
- Role-based access control (RBAC)
- Realtime activity architecture
- Modular Laravel service architecture

---

# Enterprise Features

## AI-Powered Shopping Assistant

Integrated conversational AI assistant powered by Ollama + Qwen for intelligent product discovery and customer assistance.

Capabilities include:

- Natural language product search
- Multilingual customer interaction
- Product recommendation workflows
- Catalog-aware AI responses
- Intelligent customer guidance
- Storefront-aware assistance system

---

## Image-Based Product Recognition

Enterprise visual search workflow allowing customers to discover products using uploaded images.

Features include:

- AI-assisted image recognition
- Product similarity matching
- Catalog-integrated image discovery
- Dual-access search workflow
- Storefront and chatbot integration

---

## Unified Inventory Management

Advanced inventory infrastructure designed for operational retail workflows.

Features include:

- Live stock synchronization
- Manual stock movement tracking
- Batch inventory import engine
- Inventory audit history
- Low-stock monitoring
- Variant-based inventory architecture
- Stock movement logging
- Operational inventory reporting

---

## Walk-In POS Infrastructure

Integrated point-of-sale workflow for physical retail operations.

Capabilities include:

- Walk-in customer processing
- Unified sales synchronization
- Shared inventory architecture
- Receipt-ready workflows
- Guest transaction handling
- Retail operational support

---

## Enterprise Reporting System

Operational reporting infrastructure with export-ready workflows.

Supported exports:

- CSV
- XLSX
- PDF

Report coverage includes:

- Sales reports
- Inventory reporting
- Operational analytics
- Audit log monitoring
- Business activity summaries

---

## Security & Operational Integrity

The platform follows enterprise-oriented Laravel security practices and operational safeguards.

Security highlights include:

- CSRF protection
- Secure authentication workflows
- Session management protection
- Role-based authorization
- Request validation architecture
- Middleware-based route protection
- Safe admin route boundaries
- Operational audit logging
- Controlled inventory transactions
- Error handling infrastructure
- Secure export workflows
- Input sanitization and validation
- Protected operational services
- Structured backend service layers

---

# System Architecture

The platform follows a modular Laravel architecture using:

- Controllers
- Services
- Policies
- Form Requests
- Events & Listeners
- Middleware
- Resourceful Routing
- Modular Route Separation
- Reusable Blade Components
- Service-Oriented Business Logic

---

# Technology Stack

| Layer | Technology |
|---|---|
| Backend Framework | Laravel 12 |
| Frontend | Blade + Tailwind CSS |
| Database | MariaDB |
| AI Integration | Ollama + Qwen |
| Reporting | Laravel Excel + PDF Exports |
| Authentication | Laravel Authentication System |
| Build Tools | Vite |
| Runtime | PHP 8+ |

---

# Route Architecture

| Route Group | Purpose |
|---|---|
| `routes/storefront.php` | Public storefront workflows |
| `routes/auth.php` | Authentication workflows |
| `routes/admin.php` | Admin operational workflows |
| `routes/api.php` | API integrations and operational endpoints |

---

# Quick Start

## Install Dependencies

```bash
composer install
npm install
```

---

## Configure Environment

Copy environment configuration:

```bash
cp .env.example .env
```

Configure database credentials inside `.env`.

---

## Generate Application Key

```bash
php artisan key:generate
```

---

## Run Database Migrations & Seeders

```bash
php artisan migrate
php artisan db:seed
```

---

## Link Storage

```bash
php artisan storage:link
```

---

## Start Development Environment

```bash
composer dev
```

---

# Local Development URLs

| Area | URL |
|---|---|
| Storefront | `http://localhost:8000` |
| Login | `http://localhost:8000/login` |
| Customer Account | `http://localhost:8000/account` |
| Admin Panel | `http://localhost:8000/admin` |

---

# Demo Accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@ysabelle.store` | `Password123x` |
| Customer | `customer@ysabelle.store` | `Password123x` |

---

# Development Commands

## Full Development Stack

```bash
composer dev
```

## Backend Server

```bash
composer serve:local
```

## Frontend Assets

```bash
npm run dev
```

## Full Validation Checks

```bash
composer check
```

---

# Testing

Run automated tests:

```bash
php artisan test
```

```bash
composer check
```

The testing environment uses SQLite in-memory configuration and does not require a live MariaDB instance.

---

# Project Contributors

| Name | GitHub Account | Role | Contributions |
|---|---|---|---|
| Armando R. Abarado Jr. | C1D0U | Lead Developer / System Architect / Full Stack Engineer | Led the majority of the platform architecture, backend engineering, enterprise workflow systems, AI integrations, inventory infrastructure, reporting systems, authentication architecture, security implementation, operational services, realtime systems, database design, ecommerce workflows, and full-stack enterprise application development across the platform. |
| Armando R. Abarado Jr. | HEVAB1 | Backend Systems Engineer / Enterprise Platform Contributor | Contributed to backend operational systems, enterprise validation workflows, reporting modules, retail business logic, inventory workflows, security enhancements, API integrations, operational tooling, and Laravel backend infrastructure support. |
| Joshua Daniel S. Vito | Solyuuki | Frontend Integration Contributor / Framework Setup Support | Contributed to Laravel framework environment setup, storefront workflow integration, frontend navigation systems, UI support workflows, page integration assistance, responsive layout support, interface validation, social media integration assistance, and storefront operational integration support. |
| James S. Ramos | JamesSRamos | Frontend Workflow Contributor / Integration Support Engineer | Assisted in frontend workflow integration, navigation support systems, UI consistency validation, storefront connection workflows, frontend operational support, responsive interface assistance, layout integration support, testing assistance, and collaborative frontend implementation tasks. |

---

# Operational Notes

This repository is designed for educational, demonstration, and enterprise workflow simulation purposes.

The platform demonstrates modern Laravel enterprise development practices including modular architecture, operational retail workflows, AI-assisted ecommerce experiences, inventory synchronization systems, and enterprise-oriented security patterns.

---
