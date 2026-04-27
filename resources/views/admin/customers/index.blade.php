@extends('layouts.admin', ['title' => 'Customers | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Customers"
        title="Customer directory"
        description="See registered customers and walk-in buyers from one view."
    />

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" class="ys-admin-toolbar">
            <input type="text" name="search" value="{{ $search }}" class="ys-admin-input" placeholder="Search name, email, or phone">
            <button class="ys-admin-button-secondary">Search</button>
        </form>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Registered Customers</h2>
                    <p class="ys-admin-subtle">User accounts with order history and total spend.</p>
                </div>
            </div>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Orders</th>
                            <th>Spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registeredCustomers as $customer)
                            <tr>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $customer->name }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $customer->email }}</p>
                                </td>
                                <td>{{ $customer->orders_count }}</td>
                                <td>PHP {{ number_format((float) ($customer->orders_sum_grand_total ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="ys-admin-empty-panel">No registered customers found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $registeredCustomers->links('vendor.pagination.admin') }}</div>
        </article>

        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Walk-in Buyers</h2>
                    <p class="ys-admin-subtle">Guest sales aggregated from POS receipts.</p>
                </div>
            </div>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Sales</th>
                            <th>Spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($walkInCustomers as $customer)
                            <tr>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $customer->customer_name }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $customer->customer_phone ?: 'No phone' }}</p>
                                </td>
                                <td>{{ $customer->orders_count }}</td>
                                <td>PHP {{ number_format((float) $customer->total_spend, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="ys-admin-empty-panel">No walk-in customers found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $walkInCustomers->links('vendor.pagination.admin') }}</div>
        </article>
    </section>
@endsection
