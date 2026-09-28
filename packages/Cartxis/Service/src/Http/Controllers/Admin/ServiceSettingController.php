<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Service\Http\Requests\StoreServiceSettingsRequest;
use Cartxis\Service\Services\ServiceSettings;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ServiceSettingController extends Controller
{
    public function __construct(protected ServiceSettings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('Admin/Services/Settings/Index', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(StoreServiceSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        return redirect()
            ->route('admin.services.settings.edit')
            ->with('success', 'Service settings saved successfully.');
    }
}
