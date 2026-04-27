<?php

return [
    'navigation' => [
        [
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'dashboard',
        ],
        [
            'label' => 'Products',
            'route' => 'admin.catalog.products.index',
            'icon' => 'products',
        ],
        [
            'label' => 'Categories',
            'route' => 'admin.catalog.categories.index',
            'icon' => 'categories',
        ],
        [
            'label' => 'Inventory',
            'route' => 'admin.inventory.index',
            'icon' => 'inventory',
        ],
        [
            'label' => 'Manual Stock',
            'route' => 'admin.inventory.manual-import.create',
            'icon' => 'stock-in',
        ],
        [
            'label' => 'Batch Import',
            'route' => 'admin.inventory.batch-imports.create',
            'icon' => 'upload',
        ],
        [
            'label' => 'Walk-in POS',
            'route' => 'admin.pos.create',
            'icon' => 'pos',
        ],
        [
            'label' => 'Orders',
            'route' => 'admin.orders.index',
            'icon' => 'orders',
        ],
        [
            'label' => 'Customers',
            'route' => 'admin.customers.index',
            'icon' => 'customers',
        ],
        [
            'label' => 'Reports',
            'route' => 'admin.reports.index',
            'icon' => 'reports',
        ],
    ],

    'reports' => [
        'sales' => 'Sales Report',
        'inventory' => 'Inventory Report',
        'walk_in_sales' => 'Walk-in Sales Report',
        'product_performance' => 'Product Performance Report',
    ],
];
