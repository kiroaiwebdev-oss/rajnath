<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Business;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        'contact.phone2' => ['label' => 'Second phone (E.164)', 'rules' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/']],
        'contact.street' => ['label' => 'Office street address (without city)', 'rules' => ['nullable', 'string', 'max:200']],
        'contact.locality' => ['label' => 'Area and city (e.g. Kankarbagh, Patna)', 'rules' => ['nullable', 'string', 'max:120']],
        'contact.region' => ['label' => 'State', 'rules' => ['nullable', 'string', 'max:80']],
        'contact.postal_code' => ['label' => 'PIN code', 'rules' => ['nullable', 'regex:/^[1-9][0-9]{5}$/']],
        'contact.lat' => ['label' => 'Office latitude (map pin, e.g. 25.5958)', 'rules' => ['nullable', 'numeric', 'between:-90,90']],
        'contact.lng' => ['label' => 'Office longitude (map pin, e.g. 85.1601)', 'rules' => ['nullable', 'numeric', 'between:-180,180']],
        'social.youtube' => ['label' => 'YouTube URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'social.instagram' => ['label' => 'Instagram URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'social.facebook' => ['label' => 'Facebook URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'social.x' => ['label' => 'X (Twitter) URL', 'rules' => ['nullable', 'url:https', 'max:255']],
        'seo.google_verification' => ['label' => 'Google Search Console verification code', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/']],
        'seo.bing_verification' => ['label' => 'Bing Webmaster verification code', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/']],
        'source.repository_url' => ['label' => 'AGPL source-code URL', 'rules' => ['required', 'url:https', 'max:255']],
    ];

    public function edit(SettingsRepository $settings, Business $business): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.edit', ['fields' => self::FIELDS, 'values' => $settings->all(), 'hours' => $business->hours()]);
    }

    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $this->authorize('settings.manage');
        $rules = [];
        foreach (self::FIELDS as $key => $f) {
            $rules[str_replace('.', '__', $key)] = $f['rules'];
        }
        $range = ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d\s*[-–]\s*([01]\d|2[0-3]):[0-5]\d$/u'];
        foreach (array_keys(Business::DAYS) as $day) {
            $rules["hours__{$day}_1"] = $range;
            $rules["hours__{$day}_2"] = $range;
        }
        $data = $request->validate($rules, ['hours__*.regex' => 'Write hours like 08:00-12:00 (24-hour clock).']);
        $week = [];
        foreach (array_keys(Business::DAYS) as $day) {
            foreach ([1, 2] as $n) {
                $v = preg_replace('/\s*[-–]\s*/u', '-', trim((string) ($data["hours__{$day}_{$n}"] ?? '')));
                if ($v === '') {
                    continue;
                }
                [$open, $close] = explode('-', $v);
                if ($open >= $close) {
                    throw ValidationException::withMessages(["hours__{$day}_{$n}" => 'The closing time must be after the opening time.']);
                }
                $week[$day][] = $v;
            }
            $week[$day] ??= [];
        }
        if ($settings->get('hours.week') !== $week) {
            $settings->set('hours.week', $week, false, 'hours');
        }
        foreach (self::FIELDS as $key => $f) {
            $value = $data[str_replace('.', '__', $key)] ?? null;
            if ($settings->get($key) !== $value) {
                $settings->set($key, $value);
            }
        }

        return back()->with('status', 'Settings saved.');
    }
}
