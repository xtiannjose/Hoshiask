<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CannedResponseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManage();

        $query = CannedResponse::with('createdBy:id,name')
            ->orderBy('title');

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $responses = $query->paginate(20)->withQueryString();

        return view('admin.canned-responses.index', compact('responses'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        CannedResponse::create([
            'created_by_user_id' => (int) auth()->id(),
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
        ]);

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response created.');
    }

    public function update(Request $request, CannedResponse $cannedResponse)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $cannedResponse->update([
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
        ]);

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response updated.');
    }

    public function destroy(CannedResponse $cannedResponse)
    {
        $this->authorizeManage();

        $cannedResponse->delete();

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response deleted.');
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless(config('hoshiask.features.canned_replies'), 404);

        $term = $request->string('q')->toString();

        $query = CannedResponse::query()
            ->select(['id', 'title', 'body'])
            ->orderBy('title')
            ->limit(50);

        if ($term !== '') {
            $query->search($term);
        }

        return response()->json($query->get());
    }

    private function authorizeManage(): void
    {
        abort_unless(config('hoshiask.features.canned_replies'), 404);

        $user = auth()->user();

        if (! $user || ! $user->canManageTickets()) {
            abort(403);
        }
    }
}
