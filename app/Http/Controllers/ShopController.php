<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['shop' => $request->user()->tenant->only(['id', 'name', 'phone', 'address'])]);
    }

    public function update(UpdateShopRequest $request): JsonResponse
    {
        // The authenticated membership is the only source of shop identity.
        $shop = $request->user()->tenant;
        $shop->update($request->safe()->only(['name', 'phone', 'address']));

        return response()->json(['shop' => $shop->only(['id', 'name', 'phone', 'address'])]);
    }
}
