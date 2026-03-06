<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\EbookController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\EbookImageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\User\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\StatusCheckController;


use App\Http\Controllers\Api\Admin\CouponController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\DashboardController;


// airpay 
use App\Http\Controllers\Api\AirpayController;
use App\Http\Controllers\Api\AirpayCallbackController;
use App\Http\Controllers\Api\AirpayStatusController;


use App\Http\Controllers\Api\IdfcController;
// Route::post('/register', function (Request $r){
//     return $r;
// });


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/artisan/list', [ArtisanController::class, 'list']);
    Route::post('/artisan/run', [ArtisanController::class, 'run']);
});


Route::post('/register', [UserController::class, 'register']); //
Route::post('/login', [UserController::class, 'login']); //

// ✅ ADD THESE HERE
Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);

// use App\Models\User;

// Route::get('/how/{id}', function ($id) {
//     $user = User::find($id);
//     if (!$user) {
//         return response()->json(['message' => 'User not found'], 404);
//     }
//     $user->delete();
//     return response()->json(['message' => 'User deleted successfully']);
// });

// Route::post('/add', function (Request $request) {
//     $data = DB::table('ebooks')->insert([
//             'id'=>$request->id,
//             'title'       => $request->title,
//             'slug'      => $request->slug,
//             'description'   => ($request->description),
//             'price'=>$request->price,
//             'ebook_file'=>$request->ebook_file,
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);
//     $user= DB::table('ebooks')->get();
//     return response()->json($user);
// });


// Route::get('/ha',function (){
//     return'running';
// });

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/related', [ProductController::class, 'related']);



Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}/products', [ProductController::class, 'byCategory']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);//
    Route::get('/profile', [UserController::class, 'profile']); // optional
        Route::post('/change-password', [UserController::class, 'changePassword']);

    Route::post('/checkout',[CheckoutController::class,'checkout']);
    
    Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
    Route::post('/checkout/summary', [CheckoutController::class, 'summary']);
    Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder']);
    Route::get('/order-history/{id}',[CheckoutController::class,'orderhistory']);//
    Route::get('/cart', [CartController::class, 'viewCart']);//
    Route::post('/cart/add', [CartController::class, 'addItem']);//
    Route::put('/cart/item/{id}', [CartController::class, 'updateItem']);//
    Route::delete('/cart/item/{id}', [CartController::class, 'removeItem']);//
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);
    
    Route::get('/order/{id}',[OrderController::class,'viewOrder']);
    Route::post('/order/cancel/{id}',[OrderController::class,'cancelOrder']);
    Route::get('user-downloads/{userId}', [CheckoutController::class, 'userDownloads']);
    
    
    Route::post('/generate-qr', [QRCodeController::class, 'generateQR']);
      Route::post('/check-status', [StatusCheckController::class, 'checkStatus']);
  
    
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);

    Route::get('/addresses/{id}', [AddressController::class, 'show']);
    Route::post('/addresses/{id}', [AddressController::class, 'update']); // your requirement
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
    
    Route::get('/ebooks/{id}/download', [EbookController::class, 'download']);

});


Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {

        // Ebooks
        Route::get('/ebooks', [EbookController::class, 'index']);//
        Route::post('/ebooks', [EbookController::class, 'store']);//
        Route::get('/ebooks/{id}', [EbookController::class, 'show']);//
        

        // Update: accept POST + optional _method=PUT
        Route::put('/ebooks/{id}', [EbookController::class, 'update']); // new POST route
        //Route::put('/ebooks/{id}', [EbookController::class, 'update']);  // keep PUT for REST
        //Route::put('/ebooks/{id}', [EbookController::class, 'update']);
        Route::delete('/ebooks/{id}', [EbookController::class, 'destroy']);//

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Ebook Images
        Route::get('/ebooks/{ebookId}/images', [EbookImageController::class, 'index']);
        Route::post('/ebooks/{ebookId}/images', [EbookImageController::class, 'store']);

        Route::get('/ebook-images/{id}', [EbookImageController::class, 'show']);
        Route::post('/ebook-images/{id}', [EbookImageController::class, 'update']); // file update
        Route::delete('/ebook-images/{id}', [EbookImageController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/refund', [OrderController::class, 'refund']);

        Route::get('/coupons', [CouponController::class, 'index']);
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::post('/coupons/{id}', [CouponController::class, 'update']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::put('/users/{id}', [UserManagementController::class, 'update']);
        Route::get('/users/{id}/orders', [UserManagementController::class, 'orders']);
        // routes/api.php
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
        
        // Dashboard Routes
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/sales', [DashboardController::class, 'sales']);
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity']); // Optional
 

    });


    Route::post('/generateQR', [AirpayController::class, 'generateQR']);
    Route::any('/checkstatus', [AirpayStatusController::class, 'checkStatus']);
    
     Route::post('/airpayipn', [AirpayCallbackController::class, 'AirpayIpn']);
     Route::post('/airpaycallback', [AirpayCallbackController::class, 'Airpaycallback']);

// IDFC 
    Route::post('/hdfcpayout', [IdfcController::class, 'generateQR']);
    
    
    

    