<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function index()
    {
        // Assuming the user is authenticated and you have a user_id
        $userId = auth()->id();

        // Fetch the cart for the user
        $cart = Cart::where('user_id', $userId)->first();

        // If the cart doesn't exist, create a new one
        if (! $cart) {
            $cart = Cart::create([
                'user_id' => $userId,
                'cart_items' => [],
            ]);
        }

        // Ensure cart_items is an array
        $cartItems = is_array($cart->cart_items) ? $cart->cart_items : [];

        // Fetch the user's tier
        $user = User::find($userId);
        $userTier = $user->user_tier;

        // Calculate total number of items and total price
        $totalItems = 0;
        $totalPrice = 0;

        // Array to hold the updated cart items with product details
        $updatedCartItems = [];

        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $totalItems += $item['quantity'];

                // Find the price based on the user's tier
                $price = collect(json_decode($product->price))->firstWhere('user_tier', $userTier);
                if ($price) {
                    $totalPrice += $price->amount * $item['quantity'];
                } else {
                    // Log or handle the case where the price is not found
                    Log::warning("Price not found for product ID: {$item['product_id']}, user tier: $userTier");
                }

                // Append product details to the cart item
                $updatedCartItems[] = array_merge($item, [
                    'product_name' => $product->product_name,
                    'product_brand' => $product->product_brand,
                    'product_category' => $product->product_category,
                    'price' => $price,
                ]);
            }
        }

        // Return the cart details with updated cart items
        return response()->json([
            'cart_items' => $updatedCartItems,
            'total_items' => $totalItems,
            'total_price' => $totalPrice,
        ]);
    }

    public function addToCart(Request $request, Product $product)
    {
        // Validate the quantity input
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Get or create the user's cart
        $cart = Cart::firstOrCreate(
            ['user_id' => auth()->id()],
            ['cart_items' => json_encode([])]  // Initialize cart_items if the cart is new
        );

        // Add product to cart
        $cart->addProduct($product, $request->quantity);

        UserActivityLog::create([
            'user_id' => auth()->id(),
            'activity' => 'addToCart',
            'details' => 'User added a product to the cart at '.now(),
        ]);

        return response()->json(['message' => 'Product added to cart']);
    }

    public function removeFromCart(Product $product)
    {
        // Get the user's cart
        $cart = Cart::where('user_id', auth()->id())->firstOrFail();

        // Remove product from cart
        $cart->removeProduct($product);

        UserActivityLog::create([
            'user_id' => auth()->id(),
            'activity' => 'removeFromCart',
            'details' => 'User removed a product from the cart at '.now(),
        ]);

        return response()->json(['message' => 'Product removed from cart']);
    }

    public function checkout()
    {
        // Get the user's cart
        $cart = Cart::where('user_id', auth()->id())->firstOrFail();

        // Checkout process
        $order = $cart->checkout();

        UserActivityLog::create([
            'user_id' => auth()->id(),
            'activity' => 'checkout',
            'details' => 'User proceeded to checkout at '.now(),
        ]);

        return response()->json(['message' => 'Order placed successfully', 'order' => $order]);
    }
}
