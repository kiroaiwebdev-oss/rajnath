<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Brand & contact settings. Provider credentials get their own screen in the Deployment setup section. */
class SettingsController extends Controller
{
    public const FIELDS = [
        'brand.name' => ['label' => 'Public brand name', 'rules' => ['required', 'string', 'max:80']],
        'brand.practitioner_name' => ['label' => 'Principal practitioner name', 'rules' => ['required', 'string', 'max:120']],
        'brand.tagline' => ['label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:160']],
        'contact.phone' => ['label' => 'Phone (E.164)', 'rules' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/']],
        'contact.whatsapp' => ['label' => 'WhatsApp (E.164)', 'rules' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/']],
        'contact.email' => ['label' => 'Public email', 'rules' => ['nullable', 'email', 'max:190']],
        'contact.address' => ['label' => 'Office address', 'rules' => ['nullable', 'string', 'max:300']],
        'contact.hours' => ['label' => 'Office hours', 'rules' => ['nullable', 'string', 'max:160']],
        'social.youtube' => ['label' => 'YouTube URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'social.instagram' => ['label' => 'Instagram URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'social.facebook' => ['label' => 'Facebook URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'source.repository_url' => ['label' => 'AGPL source-code URL', 'rules' => ['required', 'url:https', 'max:255']],
    ];

    public function edit(SettingsRepository $settings): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.edit', ['fields' => self::FIELDS, 'values' => $settings->all()]);
    }

    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $this->authorize('settings.manage');
        $rules = [];
        foreach (self::FIELDS as $key => $f) {
            $rules[str_replace('.', '__', $key)] = $f['rules'];
        }
        $data = $request->validate($rules);
        foreach (self::FIELDS as $key => $f) {
            $value = $data[str_replace('.', '__', $key)] ?? null;
            if ($settings->get($key) !== $value) {
                $settings->set($key, $value);
            }
        }

        return back()->with('status', 'Settings saved.');
    }
}
