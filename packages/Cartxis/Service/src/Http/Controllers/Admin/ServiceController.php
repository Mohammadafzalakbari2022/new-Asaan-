<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Service\Http\Requests\StoreServiceRequest;
use Cartxis\Service\Http\Requests\UpdateServiceRequest;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Service::query()->with('category')->withCount('bookings');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($request->filled('service_category_id')) {
            $query->where('service_category_id', $request->get('service_category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('featured') && $request->get('featured') !== '') {
            $query->where('featured', $request->boolean('featured'));
        }

        if ($request->has('booking_enabled') && $request->get('booking_enabled') !== '') {
            $query->where('booking_enabled', $request->boolean('booking_enabled'));
        }

        $sortBy = in_array($request->get('sort_by'), ['name', 'price', 'sort_order', 'created_at'], true)
            ? $request->get('sort_by')
            : 'sort_order';

        $sortOrder = $request->get('sort_order') === 'desc' ? 'desc' : 'asc';

        $services = $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('name')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'categories' => $this->categoryOptions(),
            'filters' => $request->only([
                'search', 'service_category_id', 'status', 'featured',
                'booking_enabled', 'sort_by', 'sort_order', 'per_page',
            ]),
            'stats' => $this->stats(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Services/Create', [
            'categories' => $this->categoryOptions(),
            'units' => $this->unitOptions(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['slug'] = Service::uniqueSlug(($data['slug'] ?? null) ?: $data['name']);
        $data['sort_order'] = $data['sort_order'] ?? ((int) Service::max('sort_order') + 1);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store(
                (string) config('service.image_upload_path', 'services'),
                'public'
            );
        }

        $service = Service::create($data);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service "' . $service->name . '" created successfully.');
    }

    public function edit(Service $service): Response
    {
        return Inertia::render('Admin/Services/Edit', [
            'service' => $service,
            'categories' => $this->categoryOptions(),
            'units' => $this->unitOptions(),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $this->validated($request);

        $data['slug'] = Service::uniqueSlug(($data['slug'] ?? null) ?: $data['name'], $service->id);

        if ($request->hasFile('image')) {
            $this->deleteImage($service->image);
            $data['image'] = $request->file('image')->store(
                (string) config('service.image_upload_path', 'services'),
                'public'
            );
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($service->image);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        $service->update($data);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service "' . $service->name . '" updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        // A service with jobs on it is hidden rather than removed: the jobs are
        // financial records and the storefront must not show a service the
        // owner has quietly taken down.
        if ($service->hasBookings()) {
            $service->update(['status' => 'disabled']);

            return redirect()
                ->route('admin.services.index')
                ->with(
                    'success',
                    'Service "' . $service->name . '" has bookings against it, so it was hidden rather than deleted.'
                );
        }

        $this->deleteImage($service->image);

        $service->delete();

        return back()->with('success', 'Service deleted successfully.');
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:services,id'],
            'status' => ['required', 'in:enabled,disabled'],
        ]);

        Service::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);

        return back()->with(
            'success',
            count($validated['ids']) . ' service(s) updated successfully.'
        );
    }

    /**
     * Lets the service form warn before the owner tries to save a web address
     * that is already taken.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer'],
        ]);

        $base = $validated['slug'] ?: $validated['name'];
        $slug = Service::uniqueSlug($base, $validated['service_id'] ?? null);

        return response()->json(['slug' => $slug]);
    }

    protected function categoryOptions()
    {
        return ServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(function (ServiceCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->getFullNameAttribute(),
                ];
            })
            ->values();
    }

    protected function unitOptions(): array
    {
        return collect(Service::UNITS)
            ->mapWithKeys(fn (string $unit) => [$unit => Service::UNIT_LABELS[$unit]])
            ->all();
    }

    /**
     * The four numbers the owner wants to see without opening anything.
     */
    protected function stats(): array
    {
        return [
            'total' => Service::query()->count(),
            'published' => Service::query()->enabled()->count(),
            'bookable' => Service::query()->enabled()->bookable()->count(),
            'open_jobs' => ServiceBooking::query()->open()->count(),
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validated();

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
