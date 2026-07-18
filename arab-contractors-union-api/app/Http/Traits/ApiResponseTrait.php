<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    // ─────────────────────────────────────────────────────────────────────────
    //  نجاح — مفرد أو قائمة
    // ─────────────────────────────────────────────────────────────────────────
    protected function success(
        mixed  $items   = null,
        string $message = 'تمت العملية بنجاح',
        int    $code    = 200,
    ): JsonResponse {
        $body = [
            'status'      => true,
            'message'     => $message,
            'status_code' => $code,
        ];

        if (! is_null($items)) {
            $body['items'] = $items;
        }

        return response()->json($body, $code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  نجاح مع pagination
    // ─────────────────────────────────────────────────────────────────────────
    protected function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'تمت العملية بنجاح',
    ): JsonResponse {
        return response()->json([
            'status'      => true,
            'message'     => $message,
            'status_code' => 200,
            'items'       => $paginator->items(),
            'meta'        => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  نجاح مع توكن (تسجيل دخول / تفعيل)
    // ─────────────────────────────────────────────────────────────────────────
    protected function successWithToken(
        string $token,
        mixed  $user,
        string $message   = 'تمت العملية بنجاح',
        int    $code      = 200,
        string $userKey   = 'user',
    ): JsonResponse {
        return response()->json([
            'status'      => true,
            'message'     => $message,
            'status_code' => $code,
            'items'       => [
                'token'      => $token,
                'token_type' => 'Bearer',
                $userKey     => $user,
            ],
        ], $code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  خطأ عام
    // ─────────────────────────────────────────────────────────────────────────
    protected function error(
        string  $message,
        int     $code     = 400,
        ?array  $errors   = null,
        ?string $errorKey = null,
    ): JsonResponse {
        $body = [
            'status'      => false,
            'message'     => $message,
            'status_code' => $code,
        ];

        if ($errorKey) {
            $body['error'] = $errorKey;
        }

        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  خطأ Validation (422)
    // ─────────────────────────────────────────────────────────────────────────
    protected function validationError(
        array  $errors,
        string $message = 'خطأ في البيانات المُدخلة.',
    ): JsonResponse {
        return response()->json([
            'status'      => false,
            'message'     => $message,
            'status_code' => 422,
            'errors'      => $errors,
        ], 422);
    }
}
