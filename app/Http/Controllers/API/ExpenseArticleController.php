<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExpenseArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseArticle::query()
            ->when($request->filled('active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $article = ExpenseArticle::create($this->validated($request));

        return response()->json($article, 201);
    }

    public function show(ExpenseArticle $expenseArticle)
    {
        return response()->json($expenseArticle);
    }

    public function update(Request $request, ExpenseArticle $expenseArticle)
    {
        $expenseArticle->update($this->validated($request, $expenseArticle));

        return response()->json($expenseArticle->fresh());
    }

    public function destroy(ExpenseArticle $expenseArticle)
    {
        $expenseArticle->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?ExpenseArticle $expenseArticle = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('expense_articles', 'code')->ignore($expenseArticle),
            ],
            'color' => ['nullable', 'string', 'max:24'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
