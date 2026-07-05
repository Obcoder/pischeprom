<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommodityResource;
use App\Models\Commodity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommodityController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 500));

        $query = Commodity::query()
            ->with(['avaMedia', 'expenseArticle', 'project'])
            ->withCount(['checks', 'media'])
            ->search($request->input('search'))
            ->byCheck($request->input('check_id'))
            ->hasAva($request->input('has_ava'))
            ->createdFrom($request->input('created_from'))
            ->createdTo($request->input('created_to'))
            ->applySort(
                $request->input('sort_by', 'created_at'),
                $request->input('sort_desc', true)
            );

        return CommodityResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ava' => ['nullable', 'string', 'max:2048'],
            'expense_article_id' => ['nullable', 'exists:expense_articles,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $commodity = Commodity::create($data);

        return new CommodityResource(
            $commodity
                ->load(['media', 'avaMedia'])
                ->load(['expenseArticle', 'project'])
                ->loadCount(['checks', 'media'])
        );
    }

    public function show(Commodity $commodity)
    {
        $commodity
            ->load(['media', 'avaMedia'])
            ->load(['expenseArticle', 'project'])
            ->loadCount(['checks', 'media']);

        $history = DB::table('check_commodity as cc')
            ->join('checks as ch', 'ch.id', '=', 'cc.check_id')
            ->join('commodities', 'commodities.id', '=', 'cc.commodity_id')
            ->leftJoin('entities as e', 'e.id', '=', 'ch.entity_id')
            ->leftJoin('measures as m', 'm.id', '=', 'cc.measure_id')
            ->leftJoin('expense_articles as ea', 'ea.id', '=', 'cc.expense_article_id')
            ->leftJoin('projects as p', 'p.id', '=', 'commodities.project_id')
            ->where('cc.commodity_id', $commodity->id)
            ->select([
                'cc.id',
                'cc.check_id',
                'cc.commodity_id',
                'cc.quantity',
                'cc.measure_id',
                'm.name as measure_name',
                'cc.expense_article_id',
                'ea.name as expense_article_name',
                'commodities.project_id',
                'p.name as project_name',
                'cc.price',
                'cc.total_price',
                'cc.created_at',
                'cc.updated_at',
                'ch.date as check_date',
                'ch.amount as check_amount',
                'ch.entity_id',
                'e.name as entity_name',
            ])
            ->orderByDesc('ch.date')
            ->orderByDesc('cc.id')
            ->get();

        $weekly = DB::table('check_commodity as cc')
            ->join('checks as ch', 'ch.id', '=', 'cc.check_id')
            ->where('cc.commodity_id', $commodity->id)
            ->selectRaw('YEAR(ch.date) as year, WEEK(ch.date, 3) as week, SUM(cc.total_price) as total')
            ->groupByRaw('YEAR(ch.date), WEEK(ch.date, 3)')
            ->orderBy('year')
            ->orderBy('week')
            ->get();

        $monthly = DB::table('check_commodity as cc')
            ->join('checks as ch', 'ch.id', '=', 'cc.check_id')
            ->where('cc.commodity_id', $commodity->id)
            ->selectRaw("DATE_FORMAT(ch.date, '%Y-%m') as period, SUM(cc.total_price) as total")
            ->groupByRaw("DATE_FORMAT(ch.date, '%Y-%m')")
            ->orderBy('period')
            ->get();

        $yearly = DB::table('check_commodity as cc')
            ->join('checks as ch', 'ch.id', '=', 'cc.check_id')
            ->where('cc.commodity_id', $commodity->id)
            ->selectRaw('YEAR(ch.date) as period, SUM(cc.total_price) as total')
            ->groupByRaw('YEAR(ch.date)')
            ->orderBy('period')
            ->get();

        return response()->json([
            'data' => new CommodityResource($commodity),
            'history' => $history,
            'stats' => [
                'weekly' => $weekly,
                'monthly' => $monthly,
                'yearly' => $yearly,
            ],
        ]);
    }

    public function update(Request $request, Commodity $commodity)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'ava' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'expense_article_id' => ['sometimes', 'nullable', 'exists:expense_articles,id'],
            'project_id' => ['sometimes', 'nullable', 'exists:projects,id'],
        ]);

        $commodity->update($data);

        return new CommodityResource(
            $commodity
                ->fresh(['media', 'avaMedia'])
                ->load(['expenseArticle', 'project'])
                ->loadCount(['checks', 'media'])
        );
    }

    public function destroy(Commodity $commodity)
    {
        $disk = Storage::disk(config('filesystems.unit_files_disk', 'yandex'));

        foreach ($commodity->media as $media) {
            if ($disk->exists($media->path)) {
                $disk->delete($media->path);
            }
        }

        $commodity->delete();

        return response()->json([
            'message' => 'Commodity deleted',
        ]);
    }
}
