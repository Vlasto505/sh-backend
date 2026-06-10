<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'account_type' => $user->account_type->value,
            'roles'        => $user->getRoleNames(),
        ]);
    }
}
