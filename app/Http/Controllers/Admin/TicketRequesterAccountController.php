<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\UserDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account search for the requester picker on the new-ticket form, so a long client list is searched on the server
 * instead of being sent to the browser whole. The route carries the same role rule as the form itself.
 */
class TicketRequesterAccountController extends Controller
{
    public function __construct(private UserDirectoryService $userDirectory) {}

    public function __invoke(Request $request): JsonResponse
    {
        $group = $request->string('group')->toString() === 'support' ? 'support' : 'client';
        $search = mb_substr(trim($request->string('q')->toString()), 0, 100);

        return response()->json($this->userDirectory->requesterAccounts($group, $search));
    }
}
