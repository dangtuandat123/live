<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $activeSessionIds = \App\Models\LiveSession::where('user_id', $request->user()->id)
            ->where('status', 'live')
            ->pluck('id')
            ->toArray();

        // Preload: sản phẩm nào đang live (1 query thay vì N)
        $liveProductIds = !empty($activeSessionIds)
            ? \DB::table('live_session_products')
                ->whereIn('live_session_id', $activeSessionIds)
                ->pluck('product_id')
                ->unique()
                ->toArray()
            : [];

        // Preload: đếm mentions theo product name (1 query thay vì N)
        $allProductNames = $query->clone()->pluck('name')->toArray();
        $mentionsCounts = !empty($allProductNames)
            ? \DB::table('live_events')
                ->join('live_sessions', 'live_events.live_session_id', '=', 'live_sessions.id')
                ->where('live_sessions.user_id', $request->user()->id)
                ->where('live_events.event_type', 'comment')
                ->whereIn('live_events.product_tag', $allProductNames)
                ->selectRaw('live_events.product_tag, COUNT(*) as cnt')
                ->groupBy('live_events.product_tag')
                ->pluck('cnt', 'product_tag')
            : collect();

        // Preload: đếm mentions kỳ trước (1 query thay vì N)
        $prevMentionsCounts = !empty($allProductNames)
            ? \DB::table('live_events')
                ->join('live_sessions', 'live_events.live_session_id', '=', 'live_sessions.id')
                ->where('live_sessions.user_id', $request->user()->id)
                ->where('live_events.event_type', 'comment')
                ->whereIn('live_events.product_tag', $allProductNames)
                ->where('live_events.created_at', '<', now()->subDays(7))
                ->selectRaw('live_events.product_tag, COUNT(*) as cnt')
                ->groupBy('live_events.product_tag')
                ->pluck('cnt', 'product_tag')
            : collect();

        $products = $query->get()->map(function ($product) use ($liveProductIds, $mentionsCounts, $prevMentionsCounts) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->price,
                'category' => $product->category,
                'image' => $product->image,
                'keywords' => $product->keywords ?? [],
                'mentions' => (int) ($mentionsCounts[$product->name] ?? 0),
                'prevMentions' => (int) ($prevMentionsCounts[$product->name] ?? 0),
                'isLive' => in_array($product->id, $liveProductIds),
            ];
        });

        // Lấy danh sách categories unique
        $categories = Product::where('user_id', $request->user()->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category' => $category,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,NULL,id,user_id,' . $request->user()->id],
            'price' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
        ]);

        $validated['user_id'] = $request->user()->id;

        Product::create($validated);

        return back()->with('success', 'Đã thêm sản phẩm thành công.');
    }

    public function update(Request $request, Product $product)
    {
        // Đảm bảo user chỉ sửa sản phẩm của mình
        if ($product->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,' . $product->id . ',id,user_id,' . $request->user()->id],
            'price' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
        ]);

        $product->update($validated);

        return back()->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Request $request, Product $product)
    {
        if ($product->user_id !== $request->user()->id) {
            abort(403);
        }

        $product->delete();

        return back()->with('success', 'Đã xóa sản phẩm.');
    }
}
