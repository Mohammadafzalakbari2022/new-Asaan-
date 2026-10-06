<?php

namespace Cartxis\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Cartxis\API\Helpers\ApiResponse;
use Cartxis\Service\Exceptions\ServiceBookingException;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceCategory;
use Cartxis\Service\Services\ServiceBookingService;
use Cartxis\Service\Services\ServiceSettings;
use Cartxis\Service\Services\ServiceSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Services for the mobile app.
 *
 * The shop's services are not products and never will be: nothing here goes
 * into a basket, has stock, or gets shipped. What the app needs is the same
 * thing the storefront shows -- the categories, the published services inside
 * them, the booking window, and a way to place a booking -- so this controller
 * reads the same models and the same settings the storefront does rather than
 * keeping a second copy of the rules.
 *
 * Reads are public. Booking is public too, because the website lets a guest
 * book with a phone number and the app must not be stricter than the site.
 */
class ServiceController extends Controller
{
    public function __construct(
        protected ServiceSettings $settings,
        protected ServiceBookingService $bookings,
        protected ServiceSlotService $slots,
    ) {}

    /**
     * The services list: every published service, with the same filters the
     * storefront offers.
     */
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->published()
            ->with('category')
            ->search($request->get('search'))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $request->get('category'))
            ))
            ->when($request->filled('featured'), fn ($q) => $q->featured())
            ->when($request->get('sort') === 'price', fn ($q) => $q->orderBy('price'), fn ($q) => $q->orderBy('sort_order'))
            ->orderBy('name')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 12))))
            ->withQueryString();

        return ApiResponse::paginated($services, 'Services retrieved successfully');
    }

    /**
     * Every category a customer can browse, with how many services it holds.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->visible()
            ->withCount(['services' => fn ($q) => $q->published()])
            ->get()
            ->map(fn (ServiceCategory $category) => $this->presentCategory($category));

        return ApiResponse::success($categories, 'Service categories retrieved successfully');
    }

    /**
     * One category, and the published services inside it.
     */
    public function category(string $slug): JsonResponse
    {
        $category = ServiceCategory::query()
            ->where('slug', $slug)
            ->where('status', 'enabled')
            ->first();

        if (! $category) {
            return ApiResponse::notFound('Service category not found');
        }

        $services = Service::query()
            ->published()
            ->where('service_category_id', $category->id)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => [
                'category' => $this->presentCategory($category),
                'services' => $services->items(),
            ],
            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ]);
    }

    /**
     * One service with everything needed before booking it: the booking
     * window, the slots the owner actually offers, and the related work.
     */
    public function show(string $slug): JsonResponse
    {
        $service = Service::query()
            ->published()
            ->where('slug', $slug)
            ->with('category')
            ->first();

        if (! $service) {
            return ApiResponse::notFound('Service not found');
        }

        $related = Service::query()
            ->published()
            ->where('id', '!=', $service->id)
            ->when(
                $service->service_category_id,
                fn ($q) => $q->where('service_category_id', $service->service_category_id)
            )
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        return ApiResponse::success([
            'service' => $this->present($service),
            'related' => $related->map(fn (Service $item) => $this->present($item))->values(),
            'booking' => [
                'enabled' => $this->settings->isBookingEnabled() && $service->booking_enabled,
                'requires_login' => $this->settings->requiresLoginToBook(),
                'slots' => $this->settings->timeSlotLabels(),
                'earliest_date' => $this->slots->earliestDate()->format('Y-m-d'),
                'latest_date' => $this->slots->latestDate()->format('Y-m-d'),
            ],
            'settings' => $this->publicSettings(),
        ], 'Service retrieved successfully');
    }

    /**
     * Place a booking from the app.
     *
     * Deliberately mirrors the storefront's form rather than inventing a
     * second set of rules: same field names, same messages, same service
     * doing the work, so a slot that is full is full on both sides.
     */
    public function book(Request $request, string $slug): JsonResponse
    {
        $service = Service::query()->published()->where('slug', $slug)->first();

        if (! $service) {
            return ApiResponse::notFound('Service not found');
        }

        if ($this->settings->requiresLoginToBook() && Auth::guest()) {
            return ApiResponse::error(
                'You need to sign in before booking this service.',
                null,
                401,
                'LOGIN_REQUIRED'
            );
        }

        $validated = $request->validate($this->bookingRules(), $this->bookingMessages());

        try {
            $booking = $this->bookings->book($service, $validated, Auth::user());
        } catch (ServiceBookingException $e) {
            // A full slot or a closed window is a problem with one field, and
            // the exception says which one, so the app can put the message
            // under that field instead of in a generic banner.
            return ApiResponse::error(
                $e->getMessage(),
                [$e->field => $e->getMessage()],
                422,
                'BOOKING_NOT_AVAILABLE'
            );
        } catch (Throwable $e) {
            Log::error('API service booking failed unexpectedly', [
                'service_id' => $service->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Something went wrong on our side. Please try again, or call us.');
        }

        return ApiResponse::success([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'scheduled_date' => $booking->scheduled_date?->format('Y-m-d'),
            'scheduled_slot' => $booking->scheduled_slot,
            'service_name' => $booking->service_name,
            'price' => $booking->price_snapshot,
            'price_unit' => $booking->price_unit,
            // The phone number goes back so the app can offer a tracking view
            // for a guest, who has no account to prove themselves with.
            'customer_phone' => $booking->customer_phone,
        ], 'Your booking has been received.', 201);
    }

    /**
     * The booking windows and slots, for the date picker.
     */
    public function slots(string $slug): JsonResponse
    {
        $service = Service::query()->published()->where('slug', $slug)->first();

        if (! $service) {
            return ApiResponse::notFound('Service not found');
        }

        return ApiResponse::success([
            'slots' => $this->settings->timeSlotLabels(),
            'earliest_date' => $this->slots->earliestDate()->format('Y-m-d'),
            'latest_date' => $this->slots->latestDate()->format('Y-m-d'),
        ], 'Slots retrieved successfully');
    }

    /**
     * The slice of settings a customer is allowed to see: coverage note and
     * contact numbers. Nothing sensitive is in here.
     *
     * @return array<string, mixed>
     */
    protected function publicSettings(): array
    {
        return [
            'coverage_note' => $this->settings->coverageNote(),
            'contact_phone' => $this->settings->contactPhone(),
            'contact_whatsapp' => $this->settings->contactWhatsapp(),
        ];
    }

    /**
     * The same rules, with the same messages, as the storefront form.
     *
     * @return array<string, mixed>
     */
    protected function bookingRules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'min:6'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000', 'min:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_slot' => ['required', 'string', 'max:60'],
            'request_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function bookingMessages(): array
    {
        return [
            'customer_name.required' => 'Tell us your name so we know who to ask for.',
            'customer_phone.required' => 'A phone number is needed so the worker can call you.',
            'customer_phone.min' => 'That phone number looks too short.',
            'customer_email.email' => 'That email address is not valid.',
            'address.required' => 'Tell us the address where the work is needed.',
            'address.min' => 'Please give the full address, including the area.',
            'scheduled_date.required' => 'Choose the day you would like the work done.',
            'scheduled_date.date_format' => 'Choose a date from the calendar.',
            'scheduled_slot.required' => 'Choose a time of day.',
        ];
    }

    /**
     * One service as the app should read it.
     *
     * @return array<string, mixed>
     */
    protected function present(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'short_description' => $service->short_description,
            'description' => $service->description,
            'includes' => $service->includes ?? [],
            'excludes' => $service->excludes ?? [],
            'icon' => $service->icon,
            'icon_only' => $service->icon_only,
            'image' => $service->image,
            'image_url' => $service->image_url,
            'price' => $service->price,
            'price_unit' => $service->price_unit,
            'price_display' => $service->price_display,
            'price_note' => $service->price_note,
            'duration_minutes' => $service->duration_minutes,
            'duration_display' => $service->duration_display,
            'service_area' => $service->service_area,
            'featured' => $service->featured,
            'booking_enabled' => $service->booking_enabled,
            'sort_order' => $service->sort_order,
            'category' => $service->category
                ? $this->presentCategory($service->category)
                : null,
        ];
    }

    /**
     * One category as the app should read it.
     *
     * @return array<string, mixed>
     */
    protected function presentCategory(ServiceCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'image' => $category->image,
            'services_count' => $category->services_count ?? $category->services()->published()->count(),
            'sort_order' => $category->sort_order,
        ];
    }
}
