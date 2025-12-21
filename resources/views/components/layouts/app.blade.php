<!DOCTYPE html>
<html lang="en">

<head data-theme="bumblebee">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased">
    <x-mary-nav sticky full-width>

        <x-slot:brand>
            {{-- Drawer toggle for "main-drawer" --}}
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-mary-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            {{-- Dynamic Company Brand --}}
            @php
            $company = \App\Models\CompanyProfile::current();
            @endphp

            <div class="flex items-center gap-3">
                {{-- Company Logo --}}
                @if($company->exists && $company->logo_path)
                <div class="w-8 h-8 rounded-lg overflow-hidden bg-base-200 flex-shrink-0">
                    <img src="{{ $company->logo_url }}"
                        alt="{{ $company->name ?? 'Company Logo' }}"
                        class="w-full h-full object-contain" />
                </div>
                @else
                {{-- Fallback Icon --}}
                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <x-mary-icon name="o-building-office-2" class="w-5 h-5 text-primary" />
                </div>
                @endif

                {{-- Company Name --}}
                <div class="flex flex-col">
                    <span class="font-bold text-base-content leading-tight">
                        {{ $company->exists ? $company->name : config('app.name', 'ERP System') }}
                    </span>
                    @if($company->exists && $company->name)
                    <span class="text-xs text-base-content/60 leading-none">
                        Business Management
                    </span>
                    @endif
                </div>
            </div>
        </x-slot:brand>
        {{-- Right side actions --}}
        <x-slot:actions>
            <x-mary-dropdown>
                <x-slot:trigger>
                    <x-mary-button icon="o-user" class="btn-circle" />
                </x-slot:trigger>

                <x-mary-menu-item title="Profile" link="{{ route('profile.edit') }}" />
                <x-mary-menu-item title="Logout" onclick="handleLogout()" />
            </x-mary-dropdown>
            <livewire:notification-center />
            <x-mary-theme-toggle
                darkTheme="abyss"
                lightTheme="cupcake"
                default="abyss" />
        </x-slot:actions>
    </x-mary-nav>
    {{-- The main content with `full-width` --}}
    <x-mary-main with-nav full-width>

        {{-- This is a sidebar that works also as a drawer on small screens --}}
        {{-- Notice the `main-drawer` reference here --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">

            {{-- User --}}
            @if($user = auth()->user())
            <x-mary-list-item
                :item="$user"
                value="name"
                sub-value="email"
                avatar="avatar_url"
                no-separator
                no-hover
                class="pt-2">
            </x-mary-list-item>
            <x-mary-menu-separator />
            @endif

            {{-- Activates the menu item when a route matches the `link` property --}}
            <x-mary-menu activate-by-route>
                {{-- Dashboard --}}
                <x-mary-menu-item title="Dashboard" icon="o-home" link="/dashboard" />

                <x-mary-menu-separator />

                {{-- Stock Management --}}
                <x-mary-menu-sub title="Stock Management" icon="o-archive-box">
                    <x-mary-menu-item title="Categories" icon="o-squares-plus" link="/categories" />
                    <x-mary-menu-item title="Products" icon="o-cube-transparent" link="/products" />
                    <x-mary-menu-item title="Inventory" icon="o-cube" link="/inventory" />
                    {{-- <x-mary-menu-item title="Stock Adjustments" icon="o-adjustments-horizontal" link="/stock-adjustments" /> --}}
                </x-mary-menu-sub>

                {{-- Purchase Management --}}
                <x-mary-menu-sub title="Purchase Management" icon="o-shopping-cart">
                    <x-mary-menu-item title="Suppliers" icon="o-truck" link="/suppliers" />
                    {{-- <x-mary-menu-item title="Purchase Orders" icon="o-shopping-cart" link="/purchase-orders" />
                    <x-mary-menu-item title="Challans" icon="o-clipboard-document-list" link="/challans" />
                    <x-mary-menu-item title="Purchase Reports" icon="o-chart-bar" link="/purchase-reports" /> --}}
                </x-mary-menu-sub>

                {{-- Sales Management --}}
                <x-mary-menu-sub title="Sales Management" icon="o-banknotes">
                    <x-mary-menu-item title="Clients" icon="o-users" link="/clients" />
                    <x-mary-menu-item title="Invoices" icon="o-document-text" link="/invoices" />
                    {{-- <x-mary-menu-item title="Sales Orders" icon="o-shopping-bag" link="/sales-orders" /> --}}
                    <x-mary-menu-item title="Sales Reports" icon="o-presentation-chart-line" link="/sales-reports" />
                </x-mary-menu-sub>

                {{-- Expense Managementpets --}}
                <x-mary-menu-item title="Expenses" icon="o-receipt-percent" link="/expenses" />

                <x-mary-menu-separator />

                {{-- Financial Management --}}
                <x-mary-menu-sub title="Financial Management" icon="o-currency-rupee">
                    <x-mary-menu-item title="Payments" icon="o-credit-card" link="/payments" />
                    <x-mary-menu-item title="Outstanding" icon="o-exclamation-triangle" link="/outstanding" />
                    <x-mary-menu-item title="Cash Flow" icon="o-arrows-right-left" link="/cash-flow" />
                </x-mary-menu-sub>

                {{-- Reports & Analytics --}}
                <x-mary-menu-sub title="Reports & Analytics" icon="o-chart-pie">
                    <x-mary-menu-item title="Daily Summary" icon="o-chart-bar-square" link="/daily-summary" />
                    <x-mary-menu-item title="Sales Analytics" icon="o-chart-bar" link="/sales-analytics" />
                 {{--   <x-mary-menu-item title="Financial Reports" icon="o-calculator" link="/reports/financial" />
                    <x-mary-menu-item title="Business Overview" icon="o-presentation-chart-bar" link="/reports/overview" /> --}}
                </x-mary-menu-sub>

                <x-mary-menu-separator />

                {{-- Settings --}}
                <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth">
                    <x-mary-menu-item title="Company Profile" icon="o-building-office" link="/settings/company" />
                    <x-mary-menu-item title="User Management" icon="o-user-group" link="/settings/users" />
                    <x-mary-menu-item title="System Settings" icon="o-adjustments-vertical" link="/settings/system" />
                    <x-mary-menu-item title="Backup & Export" icon="o-arrow-down-tray" link="/settings/backup" />
                </x-mary-menu-sub>
            </x-mary-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
        </x-main>

        {{-- TOAST area --}}
        <x-mary-toast />

        <script>
            function handleLogout() {
                if (confirm('Are you sure you want to logout?')) {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('logout') }}';

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';

                    form.appendChild(csrfToken);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        </script>
</body>

</html>