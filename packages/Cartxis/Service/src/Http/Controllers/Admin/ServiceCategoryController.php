<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Service\Http\Requests\StoreServiceCategoryRequest;
use Cartxis\Service\Http\Requests\UpdateServiceCategoryRequest;
use Cartxis\Service\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ServiceCategory::query()
            ->with('parent')
            ->withCount(['services', 'children']);

        if ($search = $request->get('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('slug', 'like', $like));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $categories = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        return Inertia::render('Admin/Services/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Services/Categories/Create', [
            'categories' => $this->parentOptions(),
        ]);
    }

    public function store(StoreServiceCategoryRequest $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['slug'] = ServiceCategory::uniqueSlug(($data['slug'] ?? null) ?: $data['name']);
        $data['sort_order'] = $data['sort_order'] ?? ((int) ServiceCategory::max('sort_order') + 1);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store(
                (string) config('service.category_image_upload_path', 'service-categories'),
                'public'
            );
        }

        ServiceCategory::create($data);

        return redirect()
            ->route('admin.services.categories.index')
            ->with('success', 'Service category created successfully.');
    }

    public function edit(ServiceCategory $category): Response
    {
        return Inertia::render('Admin/Services/Categories/Edit', [
            'category' => $category,
            'categories' => $this->parentOptions($category->id),
        ]);
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $category): RedirectResponse
    {
        $data = $this->validated($request);

        $data['slug'] = ServiceCategory::uniqueSlug(($data['slug'] ?? null) ?: $data['name'], $category->id);

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image);
            $data['image'] = $request->file('image')->store(
                (string) config('service.category_image_upload_path', 'service-categories'),
                'public'
            );
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($category->image);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        $category->update($data);

        return redirect()
            ->route('admin.services.categories.index')
            ->with('success', 'Service category updated successfully.');
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        // A category is never taken out from under a live service, or out from
        // under a sub-category that still holds services of its own.
        if ($category->hasServicesAnywhere()) {
            return back()->withErrors([
                'error' => 'This category still has services in it. Move or delete them first.',
            ]);
        }

        $this->deleteImage($category->image);

        $category->delete();

        return back()->with('success', 'Service category deleted successfully.');
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:service_categories,id'],
            'status' => ['required', 'in:enabled,disabled'],
        ]);

        ServiceCategory::whereIn('id', $validated['ids'])
            ->update(['status' => $validated['status']]);

        return back()->with(
            'success',
            count($validated['ids']) . ' categor' . (count($validated['ids']) === 1 ? 'y' : 'ies') . ' updated.'
        );
    }

    /**
     * The categories that may be chosen as a parent.
     *
     * A category is never offered as its own parent, and never as the parent of
     * one of its own descendants, so the tree cannot be walked into a circle.
     */
    protected function parentOptions(?int $excludeId = null)
    {
        $categories = ServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        if ($excludeId === null) {
            return $categories->values();
        }

        $blocked = array_merge(
            [(int) $excludeId],
            ServiceCategory::findOrFail($excludeId)->descendantIds()
        );

        return $categories
            ->reject(fn (ServiceCategory $category) => in_array((int) $category->id, $blocked, true))
            ->values();
    }

    protected function validated(Request $request): array
    {
        $data = $request->validated();

        // The image is handled separately, so it never reaches the model as a
        // file object.
        unset($data['image'], $data['remove_image']);

        return $data;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
