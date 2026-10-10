<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HandlesAdminReturnToRedirects
{
    protected function returnPathFromRequest(Request $request): ?string
    {
        $returnTo = trim($request->string('return_to')->toString());
        if ($returnTo === '' || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return null;
        }

        return $returnTo;
    }

    protected function redirectBackOrReturnTo(Request $request)
    {
        $returnPath = $this->returnPathFromRequest($request);
        if ($returnPath !== null) {
            return redirect()->to($returnPath);
        }

        return redirect()->back();
    }
}
