<?php

use App\Http\Controllers\AdditionController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryAlertController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestaurantDispatchController;
use App\Http\Controllers\RestaurantOrderController;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalePrintController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Rutas de invitados (no autenticados)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'createForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])->name('password.update');
});

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Cambio obligatorio de contraseña
    Route::get('/change-password', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('password.update');
    Route::get('/password/change', [ChangePasswordController::class, 'show']);
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

    // Rutas que requieren tenant activo (negocio)
    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Categorías
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

        // Punto de Venta (POS)
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/sale', [PosController::class, 'store'])->name('pos.store');

        // Módulo Restaurante: Salón, Mesas y Despacho
        Route::prefix('restaurant')->name('restaurant.')->group(function () {
            // Salón y Mesas
            Route::get('/tables', [RestaurantTableController::class, 'index'])->name('tables.index');
            Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
            Route::post('/tables/{table}/open', [RestaurantOrderController::class, 'open'])->name('orders.open');
            Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/change-table', [RestaurantOrderController::class, 'changeTable'])->name('orders.change-table');
            Route::post('/orders/{order}/items', [RestaurantOrderController::class, 'addItems'])->name('orders.add-items');
            Route::post('/orders/{order}/pre-bill', [RestaurantOrderController::class, 'preBill'])->name('orders.pre-bill');
            Route::post('/orders/{order}/settle', [RestaurantOrderController::class, 'settle'])->name('orders.settle');
            Route::post('/orders/{order}/split-settle', [RestaurantOrderController::class, 'splitAndSettle'])->name('orders.split-settle');
            Route::match(['get', 'post'], '/orders/{order}/pre-bill-partial', [RestaurantOrderController::class, 'printPartialPreBill'])->name('orders.pre-bill-partial');
            Route::post('/orders/{order}/cancel', [RestaurantOrderController::class, 'cancel'])->name('orders.cancel');
            Route::match(['post', 'delete'], '/orders/{order}/items/{item}/cancel', [RestaurantOrderController::class, 'cancelItem'])->name('orders.items.cancel');

            // Despacho (Domicilios / Para Llevar)
            Route::get('/dispatch', [RestaurantDispatchController::class, 'index'])->name('dispatch.index');
            Route::post('/dispatch', [RestaurantDispatchController::class, 'store'])->name('dispatch.store');
            Route::patch('/orders/{order}/status', [RestaurantDispatchController::class, 'updateStatus'])->name('dispatch.update-status');
        });

        // Impresión Térmica de 80 mm
        Route::prefix('prints')->name('prints.')->group(function () {
            Route::get('/sales/{sale}/receipt', [SalePrintController::class, 'receipt'])->name('sales.receipt');
            Route::get('/orders/{order}/kitchen/{batch?}', [SalePrintController::class, 'kitchenTicket'])->name('orders.kitchen');
            Route::get('/orders/{order}/pre-bill', [SalePrintController::class, 'preBill'])->name('orders.pre-bill');
            Route::get('/orders/{order}/dispatch', [SalePrintController::class, 'dispatchTicket'])->name('orders.dispatch');
            Route::get('/cash-register', [CashRegisterController::class, 'printReceipt'])->name('cash-register');
        });

        // Cuadre de Caja Diario y Arqueo (SaborCaliFit)
        Route::get('/cash-register', [CashRegisterController::class, 'index'])->name('cash-register.index');
        Route::get('/reports/cash-register', fn () => redirect()->route('cash-register.index'))->name('reports.cash-register');

        // Directorio de Clientes (Operativo y Compartido para Administrador y Empleado)
        Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('throttle:60,1')->name('customers.search');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');

        // Ventas (Lectura y Registro para Administrador y Empleado)
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/print/invoice', [SaleController::class, 'printInvoice'])->name('sales.print.invoice');
        Route::get('/sales/{sale}/print/receipt', [SaleController::class, 'printReceipt'])->name('sales.print.receipt');

        // Módulo de Cartera y Crédito de Clientes (SaborCaliFit)
        Route::get('/credit/cartera', [CustomerCreditController::class, 'index'])->name('credit.index');
        Route::get('/customers/{customer}/credit', [CustomerCreditController::class, 'customerCredit'])->name('customers.credit');
        Route::post('/credit/pay', [CustomerCreditController::class, 'storePayment'])->name('credit.pay');
        Route::post('/credit/{sale}/pay', [CustomerCreditController::class, 'storePayment'])->name('credit.pay.sale');

        // API interna para búsqueda de productos (POS autocomplete)
        Route::get('/api/products/search', ProductSearchController::class)->name('api.products.search');

        // Rutas exclusivas para Dueño / Administrador (Equipo, Catálogo, Recetas, Adiciones, Proveedores, Gastos, IA, Inventario)
        Route::middleware('role:admin')->group(function () {
            // Inventario Integral y Movimientos (SaborCaliFit - Solo Administrador)
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
            Route::post('/inventory/entries', [InventoryController::class, 'storeEntry'])->name('inventory.entries.store');
            Route::post('/inventory/waste', [InventoryController::class, 'storeWaste'])->name('inventory.waste.store');
            Route::post('/inventory/adjustment', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustment.store');
            Route::get('/inventory/alerts', [InventoryAlertController::class, 'index'])->name('inventory.alerts');
            Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');

            // Gestión de Colaboradores y Roles (CaliFit Bochalema)
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

            // Asistente de Consultas en Lenguaje Natural (IA)
            Route::get('/ai/assistant', [AiAssistantController::class, 'index'])->name('ai.index');
            Route::post('/ai/ask', [AiAssistantController::class, 'ask'])->middleware('throttle:30,1')->name('ai.ask');
            Route::post('/ai/clear', [AiAssistantController::class, 'clear'])->name('ai.clear');

            // Catálogo de Productos
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            // Fórmulas de Recetas por Porción
            Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
            Route::get('/recipes/{product}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
            Route::put('/recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');

            // Adiciones Reutilizables con Precio
            Route::get('/additions', [AdditionController::class, 'index'])->name('additions.index');
            Route::get('/additions/create', [AdditionController::class, 'create'])->name('additions.create');
            Route::post('/additions', [AdditionController::class, 'store'])->name('additions.store');
            Route::get('/additions/{addition}/edit', [AdditionController::class, 'edit'])->name('additions.edit');
            Route::put('/additions/{addition}', [AdditionController::class, 'update'])->name('additions.update');
            Route::patch('/additions/{addition}/toggle-status', [AdditionController::class, 'toggleStatus'])->name('additions.toggle-status');

            // Eliminación de Clientes (Solo Administrador)
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

            // Directorio de Proveedores
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::patch('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

            // Categorías
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Gestión de Gastos y Facturas de Compra
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
            Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            Route::patch('/expenses/{expense}/pay', [ExpenseController::class, 'markAsPaid'])->name('expenses.pay');
            Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'markAsPaid']);
            Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'downloadAttachment'])->name('expenses.attachment');

            // Anulación de Ventas (Solo Administrador)
            Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

            // Módulo Completo de Reportes Gerenciales (Solo Administrador)
            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
                Route::get('/sales/export', [ReportController::class, 'exportSalesCsv'])->name('sales.export');
                Route::get('/inventory-valuation', [ReportController::class, 'inventoryValuation'])->name('inventory');
                Route::get('/inventory', [ReportController::class, 'inventoryValuation']);
                Route::get('/inventory-valuation/export', [ReportController::class, 'exportInventoryCsv'])->name('inventory.export');
                Route::get('/top-products', [ReportController::class, 'topProducts'])->name('top-products');
                Route::get('/top-products/export', [ReportController::class, 'exportTopProductsCsv'])->name('top-products.export');
            });
        });
    });
});
