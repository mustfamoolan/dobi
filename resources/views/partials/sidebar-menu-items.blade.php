<ul class="main-menu" id="all-menu-items" role="menu">
    <li class="menu-title" role="presentation">{{ __('Main') }}</li>

    <li class="slide">
        <a href="{{ route('admin.dashboard', ['page' => 'index']) }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-home-2-line"></i></span>
            <span class="side-menu__label">{{ __('Dashboard') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Inventory Management') }}</li>
    <li class="slide">
        <a href="{{ route('admin.categories.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-folder-line"></i></span>
            <span class="side-menu__label">{{ __('Categories') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.products.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-shopping-bag-3-line"></i></span>
            <span class="side-menu__label">{{ __('Products') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.warehouses.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-store-2-line"></i></span>
            <span class="side-menu__label">{{ __('Warehouses') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.stock-transfer.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-arrow-left-right-line"></i></span>
            <span class="side-menu__label">{{ __('Stock Transfer') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Sourcing & Purchases') }}</li>
    <li class="slide">
        <a href="{{ route('admin.suppliers.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-truck-line"></i></span>
            <span class="side-menu__label">{{ __('Suppliers') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.purchases.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-bill-line"></i></span>
            <span class="side-menu__label">{{ __('Purchases') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Sales & Customers') }}</li>
    <li class="slide">
        <a href="{{ route('admin.customers.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-user-heart-line"></i></span>
            <span class="side-menu__label">{{ __('Customers') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.sales.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-shopping-cart-2-line"></i></span>
            <span class="side-menu__label">{{ __('Sales') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Human Resources') }}</li>
    <li class="slide">
        <a href="{{ route('admin.employees.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-user-star-line"></i></span>
            <span class="side-menu__label">{{ __('Employees') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Finance') }}</li>
    <li class="slide">
        <a href="{{ route('admin.vouchers.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-money-dollar-circle-line"></i></span>
            <span class="side-menu__label">{{ __('Vouchers') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('Reports') }}</li>
    <li class="slide">
        <a href="{{ route('admin.reports.sales') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-bar-chart-box-line"></i></span>
            <span class="side-menu__label">{{ __('Sales Report') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.reports.purchases') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-pie-chart-line"></i></span>
            <span class="side-menu__label">{{ __('Purchases Report') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.reports.profit') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-funds-line"></i></span>
            <span class="side-menu__label">{{ __('Profit Report') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.reports.stock') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-stock-line"></i></span>
            <span class="side-menu__label">{{ __('Stock Alert Report') }}</span>
        </a>
    </li>

    <li class="menu-title" role="presentation">{{ __('System Management') }}</li>
    <li class="slide">
        <a href="{{ route('admin.users.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-group-line"></i></span>
            <span class="side-menu__label">{{ __('Users Management') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.settings.exchange-rate') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-exchange-dollar-line"></i></span>
            <span class="side-menu__label">{{ __('Exchange Rate') }}</span>
        </a>
    </li>
    <li class="slide">
        <a href="{{ route('admin.settings.index') }}" class="side-menu__item" role="menuitem">
            <span class="side_menu_icon"><i class="ri-settings-3-line"></i></span>
            <span class="side-menu__label">{{ __('Settings') }}</span>
        </a>
    </li>
</ul>