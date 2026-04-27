<?php

return [
    'navigation' => [
        [
            'group' => 'Overview',
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'dashboard',
        ],
        [
            'group' => 'Sales',
            'label' => 'Orders',
            'route' => 'admin.orders.index',
            'icon' => 'orders',
        ],
        [
            'group' => 'Sales',
            'label' => 'Walk-in POS',
            'route' => 'admin.pos.create',
            'icon' => 'pos',
        ],
        [
            'group' => 'Products',
            'label' => 'Products',
            'route' => 'admin.catalog.products.index',
            'icon' => 'products',
        ],
        [
            'group' => 'Products',
            'label' => 'Categories',
            'route' => 'admin.catalog.categories.index',
            'icon' => 'categories',
        ],
        [
            'group' => 'Stock',
            'label' => 'Stock',
            'route' => 'admin.inventory.index',
            'icon' => 'inventory',
        ],
        [
            'group' => 'CRM',
            'label' => 'Customers',
            'route' => 'admin.customers.index',
            'icon' => 'customers',
        ],
        [
            'group' => 'Insights',
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
