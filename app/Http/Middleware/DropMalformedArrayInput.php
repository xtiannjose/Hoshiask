<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Almost every field in this app is plain text, and the code that reads it expects plain text.
 * Anyone can still type `?search[]=x` or post `name[]=x`, and an array where text belongs turned
 * the page into a 500 error. Such a field is treated as missing, so the page or form answers
 * exactly as it would for an empty one.
 *
 * Only the few fields that really are lists may arrive as arrays, and only flat ones, and never
 * in a link: addresses carry text only.
 */
class DropMalformedArrayInput
{
    /** Staff pickers, ticket checkboxes and the saved-view filter set. */
    public const LIST_FIELDS = ['assigned_to', 'selected_ids', 'filters'];

    public function handle(Request $request, Closure $next): Response
    {
        $listFields = $request->isMethodSafe() ? [] : self::LIST_FIELDS;

        $bags = [$request->query, $request->request];
        if ($request->isJson()) {
            $bags[] = $request->json();
        }

        foreach ($bags as $bag) {
            $input = $bag->all();
            $kept = array_filter(
                $input,
                fn (mixed $value, int|string $field): bool => ! is_array($value)
                    || (in_array($field, $listFields, true) && ! $this->containsArray($value)),
                ARRAY_FILTER_USE_BOTH
            );

            if (count($kept) !== count($input)) {
                $bag->replace($kept);
            }
        }

        return $next($request);
    }

    /**
     * @param  array<mixed>  $list
     */
    private function containsArray(array $list): bool
    {
        foreach ($list as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }
}
