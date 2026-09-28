<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Models\ServiceCategory;
use Cartxis\Service\Services\ServiceSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected ServiceSettings $settings,
    ) {}

    /**
     * The services page: every category, then every published service.
     */
    public function index(Request $request): Response
    {
        $services = Service::query()
            ->published()
            ->with('category')
            ->search($request->get('search'))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $request->get('category'))
            ))
            ->when($request->get('sort') === 'price', fn ($q) => $q->orderBy('price'), fn ($q) => $q->orderBy('sort_order'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render($this->themeResolver->resolve('Services/Index'), [
            'services' => $services,
            'categories' => $this->categories(),
            'featured' => $this->featured(),
            'filters' => $request->only(['search', 'category', 'sort']),
            'settings' => $this->publicSettings(),
        ]);
    }

    /**
     * One category of services.
     */
    public function category(ServiceCategory $category): Response
    {
        abort_unless($category->status === 'enabled', 404);

        $services = Service::query()
            ->published()
            ->where('service_category_id', $category->id)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return Inertia::render($this->themeResolver->resolve('Services/Category'), [
            'category' => $category,
            'services' => $services,
            'categories' => $this->categories(),
            'settings' => $this->publicSettings(),
        ]);
    }

    /**
     * One service, with everything a customer needs before booking it.
     */
    public function show(Service $service): Response
    {
        abort_unless($service->status === 'enabled', 404);

        $service->load('category');

        return Inertia::render($this->themeResolver->resolve('Services/Show'), [
            'service' => $service,
            'related' => Service::query()
                ->published()
                ->where('id', '!=', $service->id)
                ->when(
                    $service->service_category_id,
                    fn ($q) => $q->where('service_category_id', $service->service_category_id)
                )
                ->orderBy('sort_order')
                ->limit(4)
                ->get(),
            'booking' => [
                'enabled' => $this->settings->isBookingEnabled() && $service->booking_enabled,
                'slots' => $this->settings->timeSlots(),
                'earliest_date' => $this->earliestDate()->format('Y-m-d'),
                'latest_date' => $this->latestDate()->format('Y-m-d'),
            ],
            'settings' => $this->publicSettings(),
        ]);
    }

    /**
     * A customer checking on a job they booked.
     *
     * The reference alone is not enough to see someone's address and notes, so
     * the phone number has to match as well.
     */
    public function track(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:30'],
        ], [
            'reference.required' => 'Enter the booking reference from your confirmation.',
            'phone.required' => 'Enter the phone number you booked with.',
        ]);

        $booking = ServiceBooking::query()
            ->whereRaw('LOWER(reference) = ?', [strtolower(trim($validated['reference']))])
            ->where(function ($q) use ($validated) {
                $q->where('customer_phone', $validated['phone'])
                    ->orWhere('customer_phone', 'like', '%' . ltrim($validated['phone'], '0') . '%');
            })
            ->first();

        if (! $booking) {
            return back()->withErrors([
                'error' => 'We could not find a job with that reference and phone number.',
            ]);
        }

        return Inertia::render($this->themeResolver->resolve('Services/Track'), [
            'booking' => $booking->load(['service', 'worker', 'events.actor']),
            'settings' => $this->publicSettings(),
        ]);
    }

    protected function categories()
    {
        return ServiceCategory::query()
            ->visible()
            ->withCount(['services' => fn ($q) => $q->published()])
            ->get();
    }

    protected function featured()
    {
        return Service::query()
            ->published()
            ->featured()
            ->with('category')
            ->orderBy('sort_order')
            ->limit(6)
            ->get();
    }

    /**
     * The slice of settings a visitor is allowed to see. Nothing here is
     * sensitive: it is the coverage note and the contact number shown on the
     * page.
     */
    protected function publicSettings(): array
    {
        return [
            'coverage_note' => $this->settings->coverageNote(),
            'contact_phone' => $this->settings->contactPhone(),
            'contact_whatsapp' => $this->settings->contactWhatsapp(),
        ];
    }

    protected function earliestDate(): CarbonImmutable
    {
        return app(\Cartxis\Service\Services\ServiceSlotService::class)->earliestDate();
    }

    protected function latestDate(): CarbonImmutable
    {
        return app(\Cartxis\Service\Services\ServiceSlotService::class)->latestDate();
    }
}
