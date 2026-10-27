<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketplaceWebhookController extends ApiController
{
    protected $orderService;

    public function __construct(MarketplaceOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function gofood(Request $request)
    {
        // Verify GoFood webhook signature (placeholder - implementasi sebenarnya memerlukan GoFood merchant key)
        // $isValid = $this->verifyGoFoodSignature($request);

        // if (! $isValid) {
        //     return $this->errorResponse('Invalid signature', 401);
        // }

        try {
            $transaction = $this->orderService->handleOrder('gofood', $request->all());

            return $this->successResponse([
                'transaction_id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'status' => 'received',
            ]);
        } catch (\Exception $e) {
            Log::error("GoFood webhook error: " . $e->getMessage());

            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function shopeeFood(Request $request)
    {
        try {
            $transaction = $this->orderService->handleOrder('shopee_food', $request->all());

            return $this->successResponse([
                'transaction_id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'status' => 'received',
            ]);
        } catch (\Exception $e) {
            Log::error("ShopeeFood webhook error: " . $e->getMessage());

            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function grabFood(Request $request)
    {
        try {
            $transaction = $this->orderService->handleOrder('grab_food', $request->all());

            return $this->successResponse([
                'transaction_id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'status' => 'received',
            ]);
        } catch (\Exception $e) {
            Log::error("GrabFood webhook error: " . $e->getMessage());

            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // Fungsi verifikasi signature (placeholder)
    // protected function verifyGoFoodSignature(Request $request): bool
    // {
    //     // GoFood menggunakan HMAC signature dengan merchant key
    //     // $signature = $request->header('X-Signature');
    //     // $computed = hash_hmac('sha256', $request->getContent(), $this->merchantKey);
    //     // return $signature === $computed;
    //     return true; // Placeholder
    // }
}