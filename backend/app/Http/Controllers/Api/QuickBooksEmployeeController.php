<?php

/**
 * QuickBooks employee listing for administrator pickers.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QboEmployeeListService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns active QuickBooks employees for the connected company.
 */
class QuickBooksEmployeeController extends Controller
{
    /**
     * Injects QuickBooks token resolution and employee listing services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboEmployeeListService  $employeeList  Employee list queries.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboEmployeeListService $employeeList,
    ) {}

    /**
     * Lists active employees from the connected QuickBooks company.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $employees = $this->employeeList->listActive($token, $request->boolean('refresh'));

        return response()->json(['data' => $employees]);
    }
}
