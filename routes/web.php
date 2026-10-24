<?php
use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ExtraController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Admin\ExtraCategoryController;
use App\Http\Controllers\Admin\ProductRecommendationController;

//rutas publicas
Route::get('/', [StorefrontController::class, 'index'])
    ->name('storefront.index');

Route::get('/platos/{product:slug}', [StorefrontController::class, 'show'])
    ->name('storefront.show');

Route::get('/carrito', [CartController::class, 'index'])
    ->name('cart.index');

Route::post('/carrito', [CartController::class, 'store'])
    ->name('cart.store');

Route::patch('/carrito/{key}', [CartController::class, 'update'])
    ->name('cart.update');

Route::delete('/carrito/{key}', [CartController::class, 'destroy'])
    ->name('cart.destroy');
//administracion    
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])
            ->name('login');

        Route::post('/login', [AuthController::class, 'login'])
            ->name('login.submit');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');
        Route::resource('categories', CategoryController::class)
            ->except(['show']);
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');
        Route::resource('extras', ExtraController::class)
            ->except(['show']);
        Route::resource('products', ProductController::class)
            ->except(['show']);
        Route::resource('extra-categories', ExtraCategoryController::class)
            ->except(['show']);
        Route::resource('products.recommendations', ProductRecommendationController::class)
            ->except(['show']);

        // Configuración general del negocio
        Route::get('/settings', [
            \App\Http\Controllers\Admin\StoreSettingController::class,
            'edit'
        ])->name('settings.edit');

        Route::put('/settings', [
            \App\Http\Controllers\Admin\StoreSettingController::class,
            'update'
        ])->name('settings.update');
        // Administración de WhatsApp
        Route::get('/whatsapp', [
            \App\Http\Controllers\Admin\WhatsappAdminController::class,
            'index'
        ])->name('whatsapp.index');

        Route::post('/whatsapp/admins', [
            \App\Http\Controllers\Admin\WhatsappAdminController::class,
            'store'
        ])->name('whatsapp.admins.store');

        Route::put('/whatsapp/admins/{admin}', [
            \App\Http\Controllers\Admin\WhatsappAdminController::class,
            'update'
        ])->name('whatsapp.admins.update');

     });

});
