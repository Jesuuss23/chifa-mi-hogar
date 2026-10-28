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
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\CheckoutQuoteController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\SocialLinkController;
use App\Http\Controllers\Admin\GoogleReviewController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\Customer\OrderRatingController;
use App\Http\Controllers\Admin\OrderRatingController as AdminOrderRatingController;
use App\Http\Controllers\Admin\ComboController;
use App\Http\Controllers\ComboStorefrontController;
use App\Http\Controllers\ComboCartController;
use App\Http\Controllers\CartCheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\Admin\DeliveryDriverController;
use App\Http\Controllers\Delivery\AuthController as DeliveryAuthController;
use App\Http\Controllers\Delivery\OrderController as DeliveryOrderController;
use App\Http\Controllers\Delivery\LocationController;
// Páginas informativas: públicas únicamente cuando están completas y publicadas.
Route::get('/aviso-legal', [\App\Http\Controllers\LegalPageController::class, 'show'])
    ->defaults('page', 'notice')->name('legal.notice');
Route::get('/privacidad', [\App\Http\Controllers\LegalPageController::class, 'show'])
    ->defaults('page', 'privacy')->name('legal.privacy');
Route::get('/cookies', [\App\Http\Controllers\LegalPageController::class, 'show'])
    ->defaults('page', 'cookies')->name('legal.cookies');

//rutas publicas
Route::get('/', [StorefrontController::class, 'index'])
    ->name('storefront.index');

Route::get('/platos/{product:slug}', [StorefrontController::class, 'show'])
    ->name('storefront.show');

Route::get('/carrito', [CartController::class, 'index'])
    ->name('cart.index');

Route::post('/carrito', [CartController::class, 'store'])
    ->name('cart.store');

Route::post('/checkout/quote', [CheckoutQuoteController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('checkout.quote');
// Registrar pedidos de clientes e invitados.
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('checkout.store');

Route::patch('/carrito/{key}', [CartController::class, 'update'])
    ->name('cart.update');

Route::delete('/carrito/{key}', [CartController::class, 'destroy'])
    ->name('cart.destroy');

Route::get('/combos', [ComboStorefrontController::class, 'index'])
    ->name('combos.index');

Route::get('/combos/{combo:slug}', [ComboStorefrontController::class, 'show'])
    ->name('combos.show');

Route::post('/carrito/combos', [ComboCartController::class, 'store'])
    ->name('cart.combos.store');
Route::get('/carrito/checkout', [CartCheckoutController::class, 'show'])
    ->name('cart.checkout');

Route::post('/carrito/checkout/cotizar', [CartCheckoutController::class, 'quote'])
    ->middleware('throttle:30,1')
    ->name('cart.checkout.quote');

Route::post('/carrito/checkout/confirmar', [CartCheckoutController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('cart.checkout.confirm');
    
Route::get('/carrito/checkout/confirmado', [CartCheckoutController::class, 'success'])
    ->name('cart.checkout.success');

Route::get('/carrito/checkout/estado', [CartCheckoutController::class, 'status'])
    ->middleware('throttle:30,1')
    ->name('cart.checkout.status');

Route::get('/pedido/seguir/{token}', [OrderTrackingController::class, 'show'])
    ->whereUuid('token')
    ->middleware('throttle:30,1')
    ->middleware(\App\Http\Middleware\ProtectOrderTrackingResponse::class)
    ->name('orders.tracking.show');

Route::get('/pedido/seguir/{token}/estado', [OrderTrackingController::class, 'status'])
    ->whereUuid('token')
    ->middleware('throttle:30,1')
    ->middleware(\App\Http\Middleware\ProtectOrderTrackingResponse::class)
    ->name('orders.tracking.status');

Route::get(
    '/pedido/seguir/{token}/ruta',
    [OrderTrackingController::class, 'route']
)
    ->whereUuid('token')
    ->middleware('throttle:15,1')
    ->middleware(\App\Http\Middleware\ProtectOrderTrackingResponse::class)
    ->name('orders.tracking.route');



// Autenticación de clientes.
Route::middleware('guest')->group(function () {
    Route::get('/registro', [CustomerAuthController::class, 'showRegister'])
        ->name('customer.register');

    Route::post('/registro', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('customer.register.store');

    Route::get('/iniciar-sesion', [CustomerAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/iniciar-sesion', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('customer.login.store');
});

// Área privada de clientes.
Route::middleware(['auth', 'customer'])->group(function () {

    Route::get('/mi-cuenta', [CustomerAuthController::class, 'account'])
        ->name('customer.account');

    Route::post('/cerrar-sesion', [CustomerAuthController::class, 'logout'])
        ->name('customer.logout');
    Route::post('/mis-pedidos/{order}/valorar', [OrderRatingController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('customer.orders.rate');
});
//administracion    
Route::prefix('admin')->name('admin.')->group(function () {
    
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])
            ->name('login');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.submit');
    });
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::patch(
            '/orders/{order}/assign-driver',
            [OrderController::class, 'assignDriver']
        )->name('orders.assign-driver');
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
        Route::put('/settings/brand', [
            \App\Http\Controllers\Admin\BrandSettingController::class,
            'update',
        ])->middleware('throttle:10,1')->name('settings.brand.update');

        Route::put('/settings/legal', [
            \App\Http\Controllers\Admin\LegalSettingController::class, 'update',
        ])->middleware('throttle:10,1')->name('settings.legal.update');
        Route::get('/legal/preview/{page}', [
            \App\Http\Controllers\LegalPageController::class, 'preview',
        ])->whereIn('page', ['notice', 'privacy', 'cookies'])->name('legal.preview');

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

        Route::get('/orders', [OrderController::class, 'index'])
            ->name('orders.index');

        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->name('orders.show');

        Route::patch('/orders/{order}/payment', [OrderController::class, 'verifyPayment'])
            ->name('orders.payment');

        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->name('orders.status');
        Route::post('/checkout', [CheckoutController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('checkout.store');
        Route::patch('/orders/{order}/collect-cash', [OrderController::class, 'collectCashPayment'])
            ->name('orders.collect-cash');

        Route::get('/social-links', [SocialLinkController::class, 'index'])
            ->name('social-links.index');

        Route::put('/social-links', [SocialLinkController::class, 'update'])
            ->name('social-links.update');

        // Reseñas de Google
        Route::get('/reviews/google', [GoogleReviewController::class, 'index'])
            ->name('reviews.google');

        Route::get('/reviews/google/data', [GoogleReviewController::class, 'data'])
            ->middleware('throttle:10,1')
            ->name('reviews.google.data');
        Route::get('/order-ratings', [AdminOrderRatingController::class, 'index'])
            ->name('order-ratings.index');
        Route::resource('combos', ComboController::class)
            ->except(['show']);
        Route::get('/delivery-drivers', [
            DeliveryDriverController::class,
            'index',
        ])->name('delivery-drivers.index');

        Route::post('/delivery-drivers', [
            DeliveryDriverController::class,
            'store',
        ])->middleware('throttle:10,1')
        ->name('delivery-drivers.store');
     });

});
Route::prefix('repartidor')
    ->name('delivery.')
    ->group(function () {

        Route::middleware('guest')->group(function () {

            Route::get('/login', [
                DeliveryAuthController::class,
                'showLogin',
            ])->name('login');

            Route::post('/login', [
                DeliveryAuthController::class,
                'login',
            ])->middleware('throttle:5,1')
              ->name('login.submit');
        });

        Route::middleware(['auth', 'delivery'])->group(function () {

            Route::post('/logout', [
                DeliveryAuthController::class,
                'logout',
            ])->name('logout');

            Route::get('/pedidos', [
                DeliveryOrderController::class,
                'index',
            ])->name('orders.index');

            Route::get('/pedidos/{order}', [
                DeliveryOrderController::class,
                'show',
            ])->name('orders.show');
            Route::post('/pedidos/{order}/gps/iniciar', [
                LocationController::class,
                'start',
            ])->middleware('throttle:10,1')
            ->name('gps.start');

            Route::post('/pedidos/{order}/gps/ubicacion', [
                LocationController::class,
                'update',
            ])->middleware('throttle:15,1')
            ->name('gps.update');

            Route::post('/pedidos/{order}/gps/detener', [
                LocationController::class,
                'stop',
            ])->middleware('throttle:10,1')
            ->name('gps.stop');
        });
    });