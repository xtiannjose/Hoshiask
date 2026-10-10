<?php

namespace App\Http\Controllers;

use App\Support\PhilippineLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Suggestions for the city/municipality box: the official cities of the province typed
 * beside it. The list is public reference data, so it only needs a signed-in session.
 */
class LocationOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // The province box asks for this when it loses focus, which is often the same click that sends the form.
        // Arriving between the form and the page it redirects to, it would use up that page's one-time
        // messages (the errors and typed values, or "Ticket created"), so it passes them on untouched.
        $request->session()->reflash();

        $province = Str::limit($request->string('province')->toString(), 120, '');

        return response()->json([
            'municipalities' => PhilippineLocations::municipalitiesFor($province),
        ]);
    }
}
