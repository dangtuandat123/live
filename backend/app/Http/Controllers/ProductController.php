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

        $products = $query->get();

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
